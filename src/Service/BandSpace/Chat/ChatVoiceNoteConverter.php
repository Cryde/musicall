<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Exception\BandSpace\UnreadableVoiceNoteException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * Turns a recorded voice note into what is stored (#974): AAC in an MP4 container, which every
 * browser plays, whatever the recording browser produced (WebM or Ogg Opus from Chrome and Firefox,
 * MP4 from Safari). Metadata goes, and ffprobe measures the result, since a WebM straight out of
 * MediaRecorder carries no duration at all. The loudness is measured too, for the waveform the player
 * draws before any audio has loaded.
 */
readonly class ChatVoiceNoteConverter
{
    public const int MAX_DURATION_SECONDS = 300;

    public const string OUTPUT_MIME_TYPE = 'audio/mp4';

    public const string MAX_UPLOAD_SIZE = '20M';

    /**
     * What libmagic reports for a MediaRecorder recording, which is not what the browser labels it:
     * Chrome's `audio/webm` reads as `video/webm` and its `audio/mp4` as `video/mp4`. Each maps to the
     * demuxer ffmpeg is forced to use, so ffmpeg never probes the input on its own and an HLS playlist
     * dressed as a recording cannot make it read other files.
     */
    private const array DEMUXER_BY_MIME_TYPE = [
        'audio/webm' => 'matroska',
        'video/webm' => 'matroska',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'mov',
        'video/mp4' => 'mov',
        'audio/x-m4a' => 'mov',
    ];

    public const array ACCEPTED_MIME_TYPES = ['audio/webm', 'video/webm', 'audio/ogg', 'audio/mp4', 'video/mp4', 'audio/x-m4a'];

    /** Mono at 64 kbit/s is plenty for a voice, and about 2.4 MB for the longest note. */
    private const string AUDIO_BITRATE = '64k';

    private const int TIMEOUT_SECONDS = 60;

    /** How many points the waveform has, whatever the note's length. */
    public const int PEAK_COUNT = 48;

    /** Scale of a peak: the loudest slice of every note reads 255, so a quiet voice still has a shape. */
    public const int PEAK_MAX = 255;

    /** Plenty to follow loudness over time, and a five minute note decodes to 2.4 MB of samples. */
    private const int PEAK_SAMPLE_RATE = 4000;

    private const int BYTES_PER_SAMPLE = 2;

    public function convert(File $recording, string $mimeType): ConvertedVoiceNote
    {
        $demuxer = self::DEMUXER_BY_MIME_TYPE[$mimeType] ?? throw new UnreadableVoiceNoteException();

        $path = tempnam(sys_get_temp_dir(), 'chat_voice_note_');
        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file for a voice note');
        }

        try {
            $this->run([
                'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error',
                '-protocol_whitelist', 'file', '-f', $demuxer, '-i', $recording->getPathname(),
                '-map', '0:a:0', '-vn', '-sn', '-dn', '-map_metadata', '-1',
                '-t', (string) self::MAX_DURATION_SECONDS,
                '-ac', '1', '-c:a', 'aac', '-b:a', self::AUDIO_BITRATE,
                '-movflags', '+faststart', '-f', 'mp4', '-y', $path,
            ]);
            $duration = (float) trim($this->run([
                'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1', $path,
            ]));
            $peaks = $this->measurePeaks($path);
        } catch (UnreadableVoiceNoteException $e) {
            unlink($path);
            throw $e;
        }

        if ($duration <= 0) {
            unlink($path);
            throw new UnreadableVoiceNoteException();
        }

        // ReplacingFile, not File: VichUploader silently skips anything that is not an upload or one.
        return new ConvertedVoiceNote(new ReplacingFile($path), max(1, (int) round($duration)), $peaks);
    }

    /**
     * The loudness of PEAK_COUNT equal slices of the stored note, as RMS scaled to 0..PEAK_MAX.
     *
     * Read from our own output rather than the upload, so it describes exactly what is played, and
     * one slice at a time, so a long note never becomes one huge array of samples.
     *
     * @return list<int>
     */
    private function measurePeaks(string $path): array
    {
        $pcm = $this->run([
            'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-f', 'mp4', '-i', $path,
            '-ac', '1', '-ar', (string) self::PEAK_SAMPLE_RATE, '-f', 's16le', '-',
        ]);

        $sampleCount = intdiv(strlen($pcm), self::BYTES_PER_SAMPLE);
        $levels = [];
        for ($slice = 0; $slice < self::PEAK_COUNT; $slice++) {
            $from = intdiv($slice * $sampleCount, self::PEAK_COUNT);
            $to = max($from + 1, intdiv(($slice + 1) * $sampleCount, self::PEAK_COUNT));
            $levels[] = $this->rms($pcm, $from, min($to, $sampleCount));
        }

        $loudest = max($levels);

        return array_map(
            static fn (float $level): int => $loudest > 0 ? (int) round($level / $loudest * self::PEAK_MAX) : 0,
            $levels,
        );
    }

    private function rms(string $pcm, int $fromSample, int $toSample): float
    {
        if ($toSample <= $fromSample) {
            return 0.0;
        }

        // `v` is little endian, like ffmpeg's s16le, whatever the host is, but unsigned: the square of
        // a sign-shifted sample is off, so the sign is restored first.
        /** @var array<int, int> $samples */
        $samples = unpack('v*', substr($pcm, $fromSample * self::BYTES_PER_SAMPLE, ($toSample - $fromSample) * self::BYTES_PER_SAMPLE));
        $sumOfSquares = 0.0;
        foreach ($samples as $sample) {
            $sample = $sample >= 0x8000 ? $sample - 0x10000 : $sample;
            $sumOfSquares += $sample * $sample;
        }

        return sqrt($sumOfSquares / count($samples));
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command): string
    {
        $process = new Process($command, timeout: self::TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessFailedException|ProcessTimedOutException $e) {
            throw new UnreadableVoiceNoteException($e);
        }

        return $process->getOutput();
    }
}

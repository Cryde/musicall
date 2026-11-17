<?php

declare(strict_types=1);

namespace App\Service\Audio;

use App\Exception\Audio\UnreadableAudioException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * Turns an uploaded sound into what is stored: AAC in an MP4 container, which every browser plays. Metadata goes,
 * ffprobe measures the result (a WebM out of MediaRecorder carries no duration), and the loudness is measured for the
 * waveform a player draws before any audio has loaded. Voice notes (#974) and a service's « Avant, après » (#1410).
 */
readonly class AudioTranscoder
{
    /** How many points the waveform has, whatever the length. */
    public const int PEAK_COUNT = 48;

    /** Scale of a peak: the loudest slice reads 255, so a quiet sound still has a shape. */
    public const int PEAK_MAX = 255;

    public const string OUTPUT_MIME_TYPE = 'audio/mp4';

    /** Plenty to follow loudness over time, and five minutes decode to 2.4 MB of samples. */
    private const int PEAK_SAMPLE_RATE = 4000;

    private const int BYTES_PER_SAMPLE = 2;

    /**
     * @param string $demuxer forced, so ffmpeg never probes the input on its own and an HLS playlist dressed as a sound
     *                        cannot make it read other files
     *
     * @throws UnreadableAudioException
     */
    public function transcode(File $input, string $demuxer, AudioEncoding $encoding): ConvertedAudio
    {
        $path = tempnam(sys_get_temp_dir(), 'audio_');
        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file for a sound');
        }

        try {
            $this->run($encoding->timeoutSeconds, [
                'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error',
                '-protocol_whitelist', 'file', '-f', $demuxer, '-i', $input->getPathname(),
                '-map', '0:a:0', '-vn', '-sn', '-dn', '-map_metadata', '-1',
                '-t', (string) $encoding->maxDurationSeconds,
                '-ac', (string) $encoding->channels, '-c:a', 'aac', '-b:a', $encoding->bitrate,
                '-movflags', '+faststart', '-f', 'mp4', '-y', $path,
            ]);
            $duration = (float) trim($this->run($encoding->timeoutSeconds, [
                'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1', $path,
            ]));
            $peaks = $this->measurePeaks($path, $encoding->timeoutSeconds);
        } catch (UnreadableAudioException $e) {
            unlink($path);
            throw $e;
        }

        if ($duration <= 0) {
            unlink($path);
            throw new UnreadableAudioException();
        }

        // ReplacingFile, not File: VichUploader silently skips anything that is not an upload or one.
        return new ConvertedAudio(new ReplacingFile($path), max(1, (int) round($duration)), $peaks);
    }

    /**
     * The loudness of PEAK_COUNT equal slices of the stored sound, as RMS scaled to 0..PEAK_MAX.
     *
     * Read from our own output rather than the upload, so it describes exactly what is played, and one slice at a
     * time, so a long sound never becomes one huge array of samples.
     *
     * @return list<int>
     */
    private function measurePeaks(string $path, int $timeoutSeconds): array
    {
        $pcm = $this->run($timeoutSeconds, [
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
    private function run(int $timeoutSeconds, array $command): string
    {
        $process = new Process($command, timeout: $timeoutSeconds);

        try {
            $process->mustRun();
        } catch (ProcessFailedException|ProcessTimedOutException $e) {
            throw new UnreadableAudioException($e);
        }

        return $process->getOutput();
    }
}

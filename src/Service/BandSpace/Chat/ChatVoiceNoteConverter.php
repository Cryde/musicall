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
 * MediaRecorder carries no duration at all.
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
            $duration = (float) $this->run([
                'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1', $path,
            ]);
        } catch (UnreadableVoiceNoteException $e) {
            unlink($path);
            throw $e;
        }

        if ($duration <= 0) {
            unlink($path);
            throw new UnreadableVoiceNoteException();
        }

        // ReplacingFile, not File: VichUploader silently skips anything that is not an upload or one.
        return new ConvertedVoiceNote(new ReplacingFile($path), max(1, (int) round($duration)));
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

        return trim($process->getOutput());
    }
}

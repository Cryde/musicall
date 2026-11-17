<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Exception\Audio\UnreadableAudioException;
use App\Exception\BandSpace\UnreadableVoiceNoteException;
use App\Service\Audio\AudioEncoding;
use App\Service\Audio\AudioTranscoder;
use App\Service\Audio\ConvertedAudio;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Turns a recorded voice note into what is stored (#974), whatever the recording browser produced (WebM or Ogg Opus
 * from Chrome and Firefox, MP4 from Safari).
 */
readonly class ChatVoiceNoteConverter
{
    public const int MAX_DURATION_SECONDS = 300;

    public const string OUTPUT_MIME_TYPE = AudioTranscoder::OUTPUT_MIME_TYPE;

    public const string MAX_UPLOAD_SIZE = '20M';

    /**
     * What libmagic reports for a MediaRecorder recording, which is not what the browser labels it:
     * Chrome's `audio/webm` reads as `video/webm` and its `audio/mp4` as `video/mp4`. Each maps to the
     * demuxer ffmpeg is forced to use.
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

    public function __construct(private AudioTranscoder $transcoder = new AudioTranscoder())
    {
    }

    public function convert(File $recording, string $mimeType): ConvertedAudio
    {
        $demuxer = self::DEMUXER_BY_MIME_TYPE[$mimeType] ?? throw new UnreadableVoiceNoteException();

        try {
            return $this->transcoder->transcode(
                $recording,
                $demuxer,
                new AudioEncoding(channels: 1, bitrate: self::AUDIO_BITRATE, maxDurationSeconds: self::MAX_DURATION_SECONDS, timeoutSeconds: self::TIMEOUT_SECONDS),
            );
        } catch (UnreadableAudioException $e) {
            throw new UnreadableVoiceNoteException($e);
        }
    }
}

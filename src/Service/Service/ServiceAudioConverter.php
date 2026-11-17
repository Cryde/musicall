<?php

declare(strict_types=1);

namespace App\Service\Service;

use App\Exception\Audio\UnreadableAudioException;
use App\Service\Audio\AudioEncoding;
use App\Service\Audio\AudioTranscoder;
use App\Service\Audio\ConvertedAudio;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Turns an « Avant, après » upload into what is stored (#1410): a mix is judged by ear, so stereo at 256 kbit/s, about
 * 10 MB for the longest.
 */
readonly class ServiceAudioConverter
{
    public const int MAX_DURATION_SECONDS = 300;

    /** A five minute 24-bit WAV is about 86 MB. */
    public const string MAX_UPLOAD_SIZE = '100M';

    /** What libmagic reports for each accepted format, mapped to the demuxer ffmpeg is forced to use. */
    private const array DEMUXER_BY_MIME_TYPE = [
        'audio/x-wav' => 'wav',
        'audio/wav' => 'wav',
        'audio/x-aiff' => 'aiff',
        'audio/aiff' => 'aiff',
        'audio/flac' => 'flac',
        'audio/x-flac' => 'flac',
        'audio/mpeg' => 'mp3',
        'audio/x-m4a' => 'mov',
        'audio/mp4' => 'mov',
    ];

    public const array ACCEPTED_MIME_TYPES = ['audio/x-wav', 'audio/wav', 'audio/x-aiff', 'audio/aiff', 'audio/flac', 'audio/x-flac', 'audio/mpeg', 'audio/x-m4a', 'audio/mp4'];

    private const string AUDIO_BITRATE = '256k';

    /** Decoding a 100 MB WAV takes longer than a voice note. */
    private const int TIMEOUT_SECONDS = 120;

    public function __construct(private AudioTranscoder $transcoder)
    {
    }

    public function convert(File $upload, string $mimeType): ConvertedAudio
    {
        $demuxer = self::DEMUXER_BY_MIME_TYPE[$mimeType] ?? throw new UnprocessableEntityHttpException('service.audio.unreadable');

        try {
            return $this->transcoder->transcode(
                $upload,
                $demuxer,
                new AudioEncoding(channels: 2, bitrate: self::AUDIO_BITRATE, maxDurationSeconds: self::MAX_DURATION_SECONDS, timeoutSeconds: self::TIMEOUT_SECONDS),
            );
        } catch (UnreadableAudioException $e) {
            throw new UnprocessableEntityHttpException('service.audio.unreadable', $e);
        }
    }
}

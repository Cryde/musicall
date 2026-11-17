<?php

declare(strict_types=1);

namespace App\Service\Audio;

/** What AudioTranscoder stores a sound as. */
final readonly class AudioEncoding
{
    public function __construct(
        public int $channels,
        /** For ffmpeg's `-b:a`, such as `64k`. */
        public string $bitrate,
        /** Longer is cut there. */
        public int $maxDurationSeconds,
        /** For each ffmpeg and ffprobe run. */
        public int $timeoutSeconds,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;

readonly class ParsedMediaUrl
{
    public function __construct(
        public MediaPlatform $platform,
        public string $embedId,
    ) {
    }
}

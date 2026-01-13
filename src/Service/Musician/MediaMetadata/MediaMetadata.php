<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaMetadata;

readonly class MediaMetadata
{
    public function __construct(
        public ?string $title = null,
        public ?string $thumbnailUrl = null,
    ) {
    }
}

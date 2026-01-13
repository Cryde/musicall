<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;

interface MediaMetadataFetcherInterface
{
    public function supports(MediaPlatform $platform): bool;

    public function fetch(string $url, string $embedId): MediaMetadata;
}

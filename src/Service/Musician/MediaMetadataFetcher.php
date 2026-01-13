<?php

declare(strict_types=1);

namespace App\Service\Musician;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaMetadata\MediaMetadata;
use App\Service\Musician\MediaMetadata\MediaMetadataFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class MediaMetadataFetcher
{
    /**
     * @param iterable<MediaMetadataFetcherInterface> $fetchers
     */
    public function __construct(
        #[TaggedIterator('app.media_metadata_fetcher')]
        private iterable $fetchers,
    ) {
    }

    public function fetch(MediaPlatform $platform, string $url, string $embedId): MediaMetadata
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($platform)) {
                return $fetcher->fetch($url, $embedId);
            }
        }

        return new MediaMetadata();
    }
}

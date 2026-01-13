<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use App\Service\Google\YoutubeVideo;

readonly class YouTubeMetadataFetcher implements MediaMetadataFetcherInterface
{
    public function __construct(
        private YoutubeVideo $youtubeVideo,
    ) {
    }

    public function supports(MediaPlatform $platform): bool
    {
        return $platform === MediaPlatform::YOUTUBE;
    }

    public function fetch(string $url, string $embedId): MediaMetadata
    {
        try {
            $videoInfo = $this->youtubeVideo->getVideoInfo($url);

            return new MediaMetadata(
                title: $videoInfo->title,
                thumbnailUrl: $videoInfo->imageUrl,
            );
        } catch (\Throwable) {
            // Fallback: generate thumbnail URL from video ID
            return new MediaMetadata(
                thumbnailUrl: "https://img.youtube.com/vi/{$embedId}/mqdefault.jpg",
            );
        }
    }
}

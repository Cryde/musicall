<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaMetadata\MediaMetadata;
use App\Service\Musician\MediaMetadata\MediaMetadataFetcherInterface;
use App\Service\Musician\MediaMetadataFetcher;
use PHPUnit\Framework\TestCase;

class MediaMetadataFetcherTest extends TestCase
{
    public function test_fetch_returns_metadata_from_matching_fetcher(): void
    {
        $expectedMetadata = new MediaMetadata('Test Video', 'https://example.com/thumb.jpg');

        $youtubeFetcher = $this->createMock(MediaMetadataFetcherInterface::class);
        $youtubeFetcher
            ->expects($this->once())
            ->method('supports')
            ->with(MediaPlatform::YOUTUBE)
            ->willReturn(true);
        $youtubeFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://youtube.com/watch?v=abc123', 'abc123')
            ->willReturn($expectedMetadata);

        $fetcher = new MediaMetadataFetcher([$youtubeFetcher]);

        $result = $fetcher->fetch(MediaPlatform::YOUTUBE, 'https://youtube.com/watch?v=abc123', 'abc123');

        $this->assertSame($expectedMetadata, $result);
    }

    public function test_fetch_skips_non_matching_fetchers(): void
    {
        $expectedMetadata = new MediaMetadata('Spotify Track', 'https://spotify.com/thumb.jpg');

        $youtubeFetcher = $this->createMock(MediaMetadataFetcherInterface::class);
        $youtubeFetcher
            ->expects($this->once())
            ->method('supports')
            ->with(MediaPlatform::SPOTIFY)
            ->willReturn(false);
        $youtubeFetcher
            ->expects($this->never())
            ->method('fetch');

        $spotifyFetcher = $this->createMock(MediaMetadataFetcherInterface::class);
        $spotifyFetcher
            ->expects($this->once())
            ->method('supports')
            ->with(MediaPlatform::SPOTIFY)
            ->willReturn(true);
        $spotifyFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://spotify.com/track/xyz', 'track/xyz')
            ->willReturn($expectedMetadata);

        $fetcher = new MediaMetadataFetcher([$youtubeFetcher, $spotifyFetcher]);

        $result = $fetcher->fetch(MediaPlatform::SPOTIFY, 'https://spotify.com/track/xyz', 'track/xyz');

        $this->assertSame($expectedMetadata, $result);
    }

    public function test_fetch_returns_empty_metadata_when_no_fetcher_matches(): void
    {
        $youtubeFetcher = $this->createMock(MediaMetadataFetcherInterface::class);
        $youtubeFetcher
            ->method('supports')
            ->willReturn(false);

        $fetcher = new MediaMetadataFetcher([$youtubeFetcher]);

        $result = $fetcher->fetch(MediaPlatform::SOUNDCLOUD, 'https://soundcloud.com/artist/track', 'artist/track');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }

    public function test_fetch_returns_empty_metadata_when_no_fetchers_available(): void
    {
        $fetcher = new MediaMetadataFetcher([]);

        $result = $fetcher->fetch(MediaPlatform::YOUTUBE, 'https://youtube.com/watch?v=abc', 'abc');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }
}

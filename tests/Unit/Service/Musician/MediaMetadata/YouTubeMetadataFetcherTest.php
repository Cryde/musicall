<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use App\Model\Publication\YoutubeVideoInfo;
use App\Service\Google\YoutubeVideo;
use App\Service\Musician\MediaMetadata\YouTubeMetadataFetcher;
use PHPUnit\Framework\TestCase;

class YouTubeMetadataFetcherTest extends TestCase
{
    private YoutubeVideo $youtubeVideo;
    private YouTubeMetadataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->youtubeVideo = $this->createMock(YoutubeVideo::class);
        $this->fetcher = new YouTubeMetadataFetcher($this->youtubeVideo);
    }

    public function test_supports_youtube_platform(): void
    {
        $this->assertTrue($this->fetcher->supports(MediaPlatform::YOUTUBE));
    }

    public function test_does_not_support_other_platforms(): void
    {
        $this->assertFalse($this->fetcher->supports(MediaPlatform::SOUNDCLOUD));
        $this->assertFalse($this->fetcher->supports(MediaPlatform::SPOTIFY));
    }

    public function test_fetch_returns_metadata_from_youtube_api(): void
    {
        $videoInfo = new YoutubeVideoInfo(
            url: 'https://youtube.com/watch?v=abc123',
            title: 'My YouTube Video',
            description: 'Video description',
            imageUrl: 'https://youtube.com/thumb.jpg',
        );

        $this->youtubeVideo
            ->expects($this->once())
            ->method('getVideoInfo')
            ->with('https://youtube.com/watch?v=abc123')
            ->willReturn($videoInfo);

        $result = $this->fetcher->fetch('https://youtube.com/watch?v=abc123', 'abc123');

        $this->assertSame('My YouTube Video', $result->title);
        $this->assertSame('https://youtube.com/thumb.jpg', $result->thumbnailUrl);
    }

    public function test_fetch_returns_fallback_thumbnail_on_api_error(): void
    {
        $this->youtubeVideo
            ->method('getVideoInfo')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->fetcher->fetch('https://youtube.com/watch?v=abc123', 'abc123');

        $this->assertNull($result->title);
        $this->assertSame('https://img.youtube.com/vi/abc123/mqdefault.jpg', $result->thumbnailUrl);
    }

    public function test_fetch_fallback_uses_embed_id_for_thumbnail(): void
    {
        $this->youtubeVideo
            ->method('getVideoInfo')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->fetcher->fetch('https://youtube.com/watch?v=xyz789', 'xyz789');

        $this->assertSame('https://img.youtube.com/vi/xyz789/mqdefault.jpg', $result->thumbnailUrl);
    }
}

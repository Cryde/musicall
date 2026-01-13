<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaMetadata\SoundCloudMetadataFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SoundCloudMetadataFetcherTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private SoundCloudMetadataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->fetcher = new SoundCloudMetadataFetcher($this->httpClient);
    }

    public function test_supports_soundcloud_platform(): void
    {
        $this->assertTrue($this->fetcher->supports(MediaPlatform::SOUNDCLOUD));
    }

    public function test_does_not_support_other_platforms(): void
    {
        $this->assertFalse($this->fetcher->supports(MediaPlatform::YOUTUBE));
        $this->assertFalse($this->fetcher->supports(MediaPlatform::SPOTIFY));
    }

    public function test_fetch_returns_metadata_with_title_and_thumbnail(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([
                'title' => 'My Track',
                'author_name' => 'Artist Name',
                'thumbnail_url' => 'https://soundcloud.com/thumb.jpg',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://soundcloud.com/oembed', [
                'query' => [
                    'url' => 'https://soundcloud.com/artist/track',
                    'format' => 'json',
                ],
                'timeout' => 5,
            ])
            ->willReturn($response);

        $result = $this->fetcher->fetch('https://soundcloud.com/artist/track', 'artist/track');

        $this->assertSame('Artist Name - My Track', $result->title);
        $this->assertSame('https://soundcloud.com/thumb.jpg', $result->thumbnailUrl);
    }

    public function test_fetch_returns_title_without_author_when_already_included(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([
                'title' => 'Artist Name - My Track',
                'author_name' => 'Artist Name',
                'thumbnail_url' => 'https://soundcloud.com/thumb.jpg',
            ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->fetcher->fetch('https://soundcloud.com/artist/track', 'artist/track');

        $this->assertSame('Artist Name - My Track', $result->title);
    }

    public function test_fetch_returns_title_only_when_no_author(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([
                'title' => 'My Track',
                'thumbnail_url' => 'https://soundcloud.com/thumb.jpg',
            ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->fetcher->fetch('https://soundcloud.com/artist/track', 'artist/track');

        $this->assertSame('My Track', $result->title);
    }

    public function test_fetch_returns_empty_metadata_on_http_error(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new \Exception('HTTP Error'));

        $result = $this->fetcher->fetch('https://soundcloud.com/artist/track', 'artist/track');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }

    public function test_fetch_returns_empty_metadata_on_invalid_response(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willThrowException(new \Exception('Invalid JSON'));

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->fetcher->fetch('https://soundcloud.com/artist/track', 'artist/track');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }
}

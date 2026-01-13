<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaMetadata\SpotifyMetadataFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SpotifyMetadataFetcherTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private SpotifyMetadataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->fetcher = new SpotifyMetadataFetcher($this->httpClient);
    }

    public function test_supports_spotify_platform(): void
    {
        $this->assertTrue($this->fetcher->supports(MediaPlatform::SPOTIFY));
    }

    public function test_does_not_support_other_platforms(): void
    {
        $this->assertFalse($this->fetcher->supports(MediaPlatform::YOUTUBE));
        $this->assertFalse($this->fetcher->supports(MediaPlatform::SOUNDCLOUD));
    }

    public function test_fetch_returns_metadata_with_title_and_thumbnail(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([
                'title' => 'My Spotify Track',
                'thumbnail_url' => 'https://spotify.com/thumb.jpg',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://open.spotify.com/oembed', [
                'query' => [
                    'url' => 'https://open.spotify.com/track/abc123',
                ],
                'timeout' => 5,
            ])
            ->willReturn($response);

        $result = $this->fetcher->fetch('https://open.spotify.com/track/abc123', 'track/abc123');

        $this->assertSame('My Spotify Track', $result->title);
        $this->assertSame('https://spotify.com/thumb.jpg', $result->thumbnailUrl);
    }

    public function test_fetch_handles_missing_fields(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $result = $this->fetcher->fetch('https://open.spotify.com/track/abc123', 'track/abc123');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }

    public function test_fetch_returns_empty_metadata_on_http_error(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new \Exception('HTTP Error'));

        $result = $this->fetcher->fetch('https://open.spotify.com/track/abc123', 'track/abc123');

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

        $result = $this->fetcher->fetch('https://open.spotify.com/track/abc123', 'track/abc123');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }
}

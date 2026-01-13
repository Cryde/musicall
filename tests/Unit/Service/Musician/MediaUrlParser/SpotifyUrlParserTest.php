<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaUrlParser\SpotifyUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpotifyUrlParserTest extends TestCase
{
    private SpotifyUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SpotifyUrlParser();
    }

    #[DataProvider('supportedUrlsProvider')]
    public function test_supports_spotify_urls(string $url): void
    {
        $this->assertTrue($this->parser->supports($url));
    }

    public static function supportedUrlsProvider(): array
    {
        return [
            'track url' => ['https://open.spotify.com/track/4i2uyexSsyLbmP8Gu2Knl9'],
            'album url' => ['https://open.spotify.com/album/4LH4d3cOWNNsVw41Gqt2kv'],
            'artist url' => ['https://open.spotify.com/artist/4Z8W4fKeB5YxbusRsdQVPb'],
            'playlist url' => ['https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M'],
            'with intl prefix' => ['https://open.spotify.com/intl-fr/track/4i2uyexSsyLbmP8Gu2Knl9'],
        ];
    }

    #[DataProvider('unsupportedUrlsProvider')]
    public function test_does_not_support_other_urls(string $url): void
    {
        $this->assertFalse($this->parser->supports($url));
    }

    public static function unsupportedUrlsProvider(): array
    {
        return [
            'youtube' => ['https://www.youtube.com/watch?v=abc123'],
            'soundcloud' => ['https://soundcloud.com/artist/track'],
            'random url' => ['https://example.com/music'],
        ];
    }

    #[DataProvider('parseUrlsProvider')]
    public function test_parse_extracts_embed_id(string $url, string $expectedEmbedId): void
    {
        $result = $this->parser->parse($url);

        $this->assertSame(MediaPlatform::SPOTIFY, $result->platform);
        $this->assertSame($expectedEmbedId, $result->embedId);
    }

    public static function parseUrlsProvider(): array
    {
        return [
            'track' => [
                'https://open.spotify.com/track/4i2uyexSsyLbmP8Gu2Knl9',
                'track/4i2uyexSsyLbmP8Gu2Knl9',
            ],
            'track with query params' => [
                'https://open.spotify.com/track/4i2uyexSsyLbmP8Gu2Knl9?si=abc123',
                'track/4i2uyexSsyLbmP8Gu2Knl9',
            ],
            'track with intl-fr prefix' => [
                'https://open.spotify.com/intl-fr/track/4i2uyexSsyLbmP8Gu2Knl9?si=d1164d6a8b0f40e8',
                'track/4i2uyexSsyLbmP8Gu2Knl9',
            ],
            'track with intl-de prefix' => [
                'https://open.spotify.com/intl-de/track/xyz789abc123',
                'track/xyz789abc123',
            ],
            'album' => [
                'https://open.spotify.com/album/4LH4d3cOWNNsVw41Gqt2kv',
                'album/4LH4d3cOWNNsVw41Gqt2kv',
            ],
            'album with intl prefix' => [
                'https://open.spotify.com/intl-fr/album/4LH4d3cOWNNsVw41Gqt2kv',
                'album/4LH4d3cOWNNsVw41Gqt2kv',
            ],
            'artist' => [
                'https://open.spotify.com/artist/4Z8W4fKeB5YxbusRsdQVPb',
                'artist/4Z8W4fKeB5YxbusRsdQVPb',
            ],
            'artist with intl prefix' => [
                'https://open.spotify.com/intl-fr/artist/4Z8W4fKeB5YxbusRsdQVPb',
                'artist/4Z8W4fKeB5YxbusRsdQVPb',
            ],
            'playlist' => [
                'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M',
                'playlist/37i9dQZF1DXcBWIGoYBM5M',
            ],
            'playlist with intl prefix' => [
                'https://open.spotify.com/intl-fr/playlist/37i9dQZF1DXcBWIGoYBM5M',
                'playlist/37i9dQZF1DXcBWIGoYBM5M',
            ],
        ];
    }

    public function test_parse_throws_exception_for_invalid_spotify_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Spotify URL');

        $this->parser->parse('https://open.spotify.com/invalid');
    }
}

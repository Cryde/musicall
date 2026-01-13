<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaUrlParser\SoundCloudUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SoundCloudUrlParserTest extends TestCase
{
    private SoundCloudUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SoundCloudUrlParser();
    }

    #[DataProvider('supportedUrlsProvider')]
    public function test_supports_soundcloud_urls(string $url): void
    {
        $this->assertTrue($this->parser->supports($url));
    }

    public static function supportedUrlsProvider(): array
    {
        return [
            'track url' => ['https://soundcloud.com/artist-name/track-name'],
            'with www' => ['https://www.soundcloud.com/artist-name/track-name'],
            'set url' => ['https://soundcloud.com/artist-name/sets/album-name'],
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
            'spotify' => ['https://open.spotify.com/track/abc123'],
            'random url' => ['https://example.com/audio'],
        ];
    }

    #[DataProvider('parseUrlsProvider')]
    public function test_parse_extracts_embed_id(string $url, string $expectedEmbedId): void
    {
        $result = $this->parser->parse($url);

        $this->assertSame(MediaPlatform::SOUNDCLOUD, $result->platform);
        $this->assertSame($expectedEmbedId, $result->embedId);
    }

    public static function parseUrlsProvider(): array
    {
        return [
            'simple track' => [
                'https://soundcloud.com/artist-name/track-name',
                'artist-name/track-name',
            ],
            'track with numbers' => [
                'https://soundcloud.com/artist123/track456',
                'artist123/track456',
            ],
            'set/playlist' => [
                'https://soundcloud.com/artist-name/sets/album-name',
                'artist-name/sets',
            ],
            'track with underscores' => [
                'https://soundcloud.com/artist_name/track_name',
                'artist_name/track_name',
            ],
        ];
    }

    public function test_parse_throws_exception_for_invalid_soundcloud_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SoundCloud URL');

        $this->parser->parse('https://soundcloud.com/');
    }
}

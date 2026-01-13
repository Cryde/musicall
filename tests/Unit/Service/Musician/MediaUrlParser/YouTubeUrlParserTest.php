<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaUrlParser\YouTubeUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YouTubeUrlParserTest extends TestCase
{
    private YouTubeUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YouTubeUrlParser();
    }

    #[DataProvider('supportedUrlsProvider')]
    public function test_supports_youtube_urls(string $url): void
    {
        $this->assertTrue($this->parser->supports($url));
    }

    public static function supportedUrlsProvider(): array
    {
        return [
            'youtube.com watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtube.com without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtu.be short' => ['https://youtu.be/dQw4w9WgXcQ'],
            'youtube.com embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'youtube.com shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
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
            'spotify' => ['https://open.spotify.com/track/abc123'],
            'soundcloud' => ['https://soundcloud.com/artist/track'],
            'vimeo' => ['https://vimeo.com/123456'],
            'random url' => ['https://example.com/video'],
        ];
    }

    #[DataProvider('parseUrlsProvider')]
    public function test_parse_extracts_video_id(string $url, string $expectedVideoId): void
    {
        $result = $this->parser->parse($url);

        $this->assertSame(MediaPlatform::YOUTUBE, $result->platform);
        $this->assertSame($expectedVideoId, $result->embedId);
    }

    public static function parseUrlsProvider(): array
    {
        return [
            'youtube.com watch' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'youtube.com watch with extra params' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLrAXtmErZgOeiKm4sgNOknGvNjby9efdf',
                'dQw4w9WgXcQ',
            ],
            'youtu.be short' => [
                'https://youtu.be/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'youtu.be with time param' => [
                'https://youtu.be/dQw4w9WgXcQ?t=30',
                'dQw4w9WgXcQ',
            ],
            'youtu.be with si param' => [
                'https://youtu.be/dQw4w9WgXcQ?si=xxx',
                'dQw4w9WgXcQ',
            ],
            'youtube.com embed' => [
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'youtube.com shorts' => [
                'https://www.youtube.com/shorts/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'video id with underscore' => [
                'https://www.youtube.com/watch?v=abc_123-xyz',
                'abc_123-xyz',
            ],
        ];
    }

    public function test_parse_throws_exception_for_invalid_youtube_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid YouTube URL');

        $this->parser->parse('https://youtube.com/invalid-url');
    }
}

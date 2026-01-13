<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaUrlParser;
use App\Service\Musician\MediaUrlParser\MediaUrlParserInterface;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use PHPUnit\Framework\TestCase;

class MediaUrlParserTest extends TestCase
{
    public function test_parse_returns_result_from_matching_parser(): void
    {
        $expectedParsedUrl = new ParsedMediaUrl(MediaPlatform::YOUTUBE, 'abc123');

        $youtubeParser = $this->createMock(MediaUrlParserInterface::class);
        $youtubeParser
            ->expects($this->once())
            ->method('supports')
            ->with('https://youtube.com/watch?v=abc123')
            ->willReturn(true);
        $youtubeParser
            ->expects($this->once())
            ->method('parse')
            ->with('https://youtube.com/watch?v=abc123')
            ->willReturn($expectedParsedUrl);

        $parser = new MediaUrlParser([$youtubeParser]);

        $result = $parser->parse('https://youtube.com/watch?v=abc123');

        $this->assertSame($expectedParsedUrl, $result);
    }

    public function test_parse_skips_non_matching_parsers(): void
    {
        $expectedParsedUrl = new ParsedMediaUrl(MediaPlatform::SPOTIFY, 'track/xyz');

        $youtubeParser = $this->createMock(MediaUrlParserInterface::class);
        $youtubeParser
            ->expects($this->once())
            ->method('supports')
            ->with('https://spotify.com/track/xyz')
            ->willReturn(false);
        $youtubeParser
            ->expects($this->never())
            ->method('parse');

        $spotifyParser = $this->createMock(MediaUrlParserInterface::class);
        $spotifyParser
            ->expects($this->once())
            ->method('supports')
            ->with('https://spotify.com/track/xyz')
            ->willReturn(true);
        $spotifyParser
            ->expects($this->once())
            ->method('parse')
            ->with('https://spotify.com/track/xyz')
            ->willReturn($expectedParsedUrl);

        $parser = new MediaUrlParser([$youtubeParser, $spotifyParser]);

        $result = $parser->parse('https://spotify.com/track/xyz');

        $this->assertSame($expectedParsedUrl, $result);
    }

    public function test_parse_returns_null_when_no_parser_matches(): void
    {
        $youtubeParser = $this->createMock(MediaUrlParserInterface::class);
        $youtubeParser
            ->method('supports')
            ->willReturn(false);

        $parser = new MediaUrlParser([$youtubeParser]);

        $result = $parser->parse('https://unknown-platform.com/video');

        $this->assertNull($result);
    }

    public function test_parse_returns_null_when_no_parsers_available(): void
    {
        $parser = new MediaUrlParser([]);

        $result = $parser->parse('https://youtube.com/watch?v=abc');

        $this->assertNull($result);
    }

    public function test_parse_trims_url_before_processing(): void
    {
        $expectedParsedUrl = new ParsedMediaUrl(MediaPlatform::YOUTUBE, 'abc123');

        $youtubeParser = $this->createMock(MediaUrlParserInterface::class);
        $youtubeParser
            ->expects($this->once())
            ->method('supports')
            ->with('https://youtube.com/watch?v=abc123')
            ->willReturn(true);
        $youtubeParser
            ->expects($this->once())
            ->method('parse')
            ->with('https://youtube.com/watch?v=abc123')
            ->willReturn($expectedParsedUrl);

        $parser = new MediaUrlParser([$youtubeParser]);

        $result = $parser->parse('  https://youtube.com/watch?v=abc123  ');

        $this->assertSame($expectedParsedUrl, $result);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaUrlParser\VimeoUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** A Vimeo link (#1411), an unlisted one keeping the hash it only plays with. */
class VimeoUrlParserTest extends TestCase
{
    private VimeoUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new VimeoUrlParser();
    }

    public function test_supports_vimeo_urls_only(): void
    {
        $this->assertTrue($this->parser->supports('https://vimeo.com/76979871'));
        $this->assertTrue($this->parser->supports('http://www.vimeo.com/76979871'));
        $this->assertTrue($this->parser->supports('https://player.vimeo.com/video/76979871'));
        $this->assertFalse($this->parser->supports('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    /** Only Vimeo's own hosts: `vimeo.com` written anywhere else in an address is not a Vimeo video. */
    #[DataProvider('lookalikeUrlsProvider')]
    public function test_lookalikes_are_not_vimeo(string $url): void
    {
        $this->assertFalse($this->parser->supports($url));
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse($url);
    }

    /** @return array<string, array{string}> */
    public static function lookalikeUrlsProvider(): array
    {
        return [
            'another host, vimeo.com in its query' => ['https://evil.example/x?vimeo.com/123456'],
            'another host, vimeo.com in its path' => ['https://evil.example/vimeo.com/123456'],
            'a host that starts like vimeo.com' => ['https://vimeo.com.evil.example/123456'],
            'a host that ends like vimeo.com' => ['https://notvimeo.com/123456'],
            'a user before the host' => ['https://vimeo.com@evil.example/123456'],
            'a user on vimeo.com' => ['https://user:pass@vimeo.com/123456'],
            'a port' => ['https://vimeo.com:8443/123456'],
            'no scheme' => ['vimeo.com/123456'],
            'another scheme' => ['javascript://vimeo.com/123456'],
        ];
    }

    #[DataProvider('parseUrlsProvider')]
    public function test_parse_extracts_the_video_and_its_hash(string $url, string $expectedEmbedId): void
    {
        $result = $this->parser->parse($url);

        $this->assertSame(MediaPlatform::VIMEO, $result->platform);
        $this->assertSame($expectedEmbedId, $result->embedId);
    }

    /** @return array<string, array{string, string}> */
    public static function parseUrlsProvider(): array
    {
        return [
            'a public video' => ['https://vimeo.com/76979871', '76979871'],
            'with a query' => ['https://vimeo.com/76979871?share=copy', '76979871'],
            'an unlisted video' => ['https://vimeo.com/76979871/8272103F6E', '76979871/8272103f6e'],
            'in a channel' => ['https://vimeo.com/channels/staffpicks/76979871', '76979871'],
            'in a group' => ['https://vimeo.com/groups/shortfilms/videos/76979871', '76979871'],
            'in a showcase' => ['https://vimeo.com/showcase/123456/video/76979871', '76979871'],
            'the player' => ['https://player.vimeo.com/video/76979871', '76979871'],
            'the player of an unlisted video' => ['https://player.vimeo.com/video/76979871?autoplay=1&h=8272103f6e', '76979871/8272103f6e'],
        ];
    }

    #[DataProvider('invalidUrlsProvider')]
    public function test_parse_refuses_what_is_not_a_video(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse($url);
    }

    /** @return array<string, array{string}> */
    public static function invalidUrlsProvider(): array
    {
        return [
            'a profile' => ['https://vimeo.com/blender'],
            'the home page' => ['https://vimeo.com/'],
            'a number inside a word' => ['https://vimeo.com/76979871abc'],
            'a player address without its video' => ['https://player.vimeo.com/76979871'],
        ];
    }
}

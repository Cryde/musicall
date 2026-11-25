<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Musician\MediaMetadata;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaMetadata\VimeoMetadataFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/** A Vimeo video's title and thumbnail, through its oEmbed endpoint (#1411). */
class VimeoMetadataFetcherTest extends TestCase
{
    public function test_supports_vimeo_only(): void
    {
        $fetcher = new VimeoMetadataFetcher(new MockHttpClient());

        $this->assertTrue($fetcher->supports(MediaPlatform::VIMEO));
        $this->assertFalse($fetcher->supports(MediaPlatform::YOUTUBE));
    }

    public function test_fetch_reads_the_title_and_the_thumbnail(): void
    {
        $requested = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): JsonMockResponse {
            $requested = $method . ' ' . $url;

            return new JsonMockResponse(['title' => 'Satellites', 'thumbnail_url' => 'https://i.vimeocdn.com/video/1.jpg']);
        });

        $result = (new VimeoMetadataFetcher($client))->fetch('https://vimeo.com/76979871', '76979871');

        $this->assertSame('GET https://vimeo.com/api/oembed.json?url=https://vimeo.com/76979871', $requested);
        $this->assertSame('Satellites', $result->title);
        $this->assertSame('https://i.vimeocdn.com/video/1.jpg', $result->thumbnailUrl);
    }

    public function test_fetch_gives_nothing_when_vimeo_does_not_answer(): void
    {
        $result = (new VimeoMetadataFetcher(new MockHttpClient(new MockResponse('', ['http_code' => 404]))))->fetch('https://vimeo.com/1', '1');

        $this->assertNull($result->title);
        $this->assertNull($result->thumbnailUrl);
    }
}

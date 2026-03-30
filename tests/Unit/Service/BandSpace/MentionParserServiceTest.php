<?php

namespace App\Tests\Unit\Service\BandSpace;

use App\Service\BandSpace\MentionParserService;
use PHPUnit\Framework\TestCase;

class MentionParserServiceTest extends TestCase
{
    private MentionParserService $service;

    protected function setUp(): void
    {
        $this->service = new MentionParserService();
    }

    public function test_extract_single_mention(): void
    {
        $result = $this->service->extractMentions('Hello @[550e8400-e29b-41d4-a716-446655440000] how are you?');
        $this->assertSame(['550e8400-e29b-41d4-a716-446655440000'], $result);
    }

    public function test_extract_multiple_mentions(): void
    {
        $text = 'Hey @[550e8400-e29b-41d4-a716-446655440000] and @[6ba7b810-9dad-11d1-80b4-00c04fd430c8] check this @[f47ac10b-58cc-4372-a567-0e02b2c3d479]';
        $result = $this->service->extractMentions($text);

        $this->assertCount(3, $result);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $result[0]);
        $this->assertSame('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $result[1]);
        $this->assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $result[2]);
    }

    public function test_extract_no_mentions(): void
    {
        $result = $this->service->extractMentions('Just a plain text message with no mentions');
        $this->assertSame([], $result);
    }

    public function test_extract_duplicate_mentions_returns_unique(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $text = "Hey @[$uuid] and again @[$uuid]";
        $result = $this->service->extractMentions($text);

        $this->assertCount(1, $result);
        $this->assertSame($uuid, $result[0]);
    }

    public function test_ignores_malformed_mentions(): void
    {
        $text = '@[not-a-uuid] @[123] @[abc] @[] regular text';
        $result = $this->service->extractMentions($text);
        $this->assertSame([], $result);
    }

    public function test_extract_adjacent_mentions(): void
    {
        $text = '@[550e8400-e29b-41d4-a716-446655440000]@[6ba7b810-9dad-11d1-80b4-00c04fd430c8]';
        $result = $this->service->extractMentions($text);

        $this->assertCount(2, $result);
    }
}

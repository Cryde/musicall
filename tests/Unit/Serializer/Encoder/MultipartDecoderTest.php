<?php declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Encoder;

use App\Serializer\Encoder\MultipartDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MultipartDecoderTest extends TestCase
{
    public function test_a_json_encoded_field_is_decoded(): void
    {
        $decoded = $this->decode(['tagIds' => '["a","b"]', 'folderId' => 'abc'], []);

        $this->assertSame(['tagIds' => ['a', 'b'], 'folderId' => 'abc'], $decoded);
    }

    public function test_a_raw_field_is_kept_as_sent_even_when_it_reads_as_json(): void
    {
        $decoded = $this->decode(
            ['content' => '["intro","couplet"]', 'tagIds' => '["a"]'],
            [MultipartDecoder::RAW_FIELDS => ['content']],
        );

        $this->assertSame(['content' => '["intro","couplet"]', 'tagIds' => ['a']], $decoded);
    }

    public function test_an_array_field_is_passed_through(): void
    {
        $decoded = $this->decode(['attachments' => ['task-1', 'note-2']], []);

        $this->assertSame(['attachments' => ['task-1', 'note-2']], $decoded);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|null
     */
    private function decode(array $fields, array $context): ?array
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], $fields));

        return (new MultipartDecoder($requestStack))->decode('', MultipartDecoder::FORMAT, $context);
    }
}

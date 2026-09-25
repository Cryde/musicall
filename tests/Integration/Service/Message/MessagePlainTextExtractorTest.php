<?php declare(strict_types=1);

namespace App\Tests\Integration\Service\Message;

use App\Service\Message\MessagePlainTextExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The real `app.plain_text_sanitizer` pipeline, which is configured in yaml and therefore cannot be
 * proven by a unit test: what it strips, and what the decode after it is there to undo.
 */
class MessagePlainTextExtractorTest extends KernelTestCase
{
    private MessagePlainTextExtractor $extractor;

    protected function setUp(): void
    {
        self::bootKernel();
        $extractor = self::getContainer()->get(MessagePlainTextExtractor::class);
        self::assertInstanceOf(MessagePlainTextExtractor::class, $extractor);
        $this->extractor = $extractor;
    }

    public function test_plain_text_comes_back_as_it_was(): void
    {
        self::assertSame('Faut penser au câble XLR', $this->extractor->extract('Faut penser au câble XLR'));
    }

    /**
     * The sanitizer allows no element at all, and an element it does not allow takes its content with
     * it rather than only losing its tags. Worth pinning: it is why a message made only of markup can
     * come back empty, and it is also exactly what the chat bubble shows, since `app.onlybr_sanitizer`
     * drops the same elements the same way. A task and the message it came from cannot disagree.
     */
    public function test_an_element_is_dropped_with_its_content(): void
    {
        self::assertSame('le', $this->extractor->extract('<b>ramener</b> le <i>câble</i>'));
    }

    public function test_a_script_leaves_nothing_behind(): void
    {
        self::assertSame('', $this->extractor->extract('<script>alert(1)</script>'));
    }

    /**
     * The half that is easy to forget: the sanitizer escapes `@`, `"` and `=`, so without the decode
     * a task seeded from a message naming somebody would be titled `&#64;batteur`.
     */
    public function test_what_the_sanitizer_escaped_is_decoded_back(): void
    {
        self::assertSame('@batteur "ok"', $this->extractor->extract('@batteur "ok"'));
    }

    public function test_line_breaks_survive(): void
    {
        self::assertSame("le câble\nles piles", $this->extractor->extract("le câble\nles piles"));
    }

    public function test_one_line_collapses_every_run_of_whitespace(): void
    {
        self::assertSame('le câble les piles', $this->extractor->extractOneLine("le câble \n\n  les piles  "));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace;

use App\Service\BandSpace\ChatMentionRenderer;
use PHPUnit\Framework\TestCase;

/**
 * The renderer's output goes straight into a `v-html`, so these are security tests as much as
 * rendering ones (#964).
 */
class ChatMentionRendererTest extends TestCase
{
    private const string ALICE = '550e8400-e29b-41d4-a716-446655440000';
    private const string BOB = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    private ChatMentionRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ChatMentionRenderer();
    }

    public function test_it_names_the_mentioned_member(): void
    {
        self::assertSame(
            'salut <span class="chat-mention">@alice</span>',
            $this->renderer->render('salut &#64;[' . self::ALICE . ']', [self::ALICE => 'alice']),
        );
    }

    public function test_it_accepts_an_unescaped_at_too(): void
    {
        // The sanitizer emits `&#64;` today, measured. Accepting the bare form as well means a change
        // in how it escapes cannot silently stop every mention rendering.
        self::assertSame(
            '<span class="chat-mention">@alice</span>',
            $this->renderer->render('@[' . self::ALICE . ']', [self::ALICE => 'alice']),
        );
    }

    public function test_a_username_cannot_carry_markup_into_the_page(): void
    {
        // The whole reason this runs server side. A username has never been through the sanitizer,
        // and autoLink on the client does no escaping of its own.
        self::assertSame(
            '<span class="chat-mention">@&lt;script&gt;alert(1)&lt;/script&gt;</span>',
            $this->renderer->render(
                '&#64;[' . self::ALICE . ']',
                [self::ALICE => '<script>alert(1)</script>'],
            ),
        );
    }

    public function test_a_quote_in_a_username_cannot_break_out_of_the_class_attribute(): void
    {
        self::assertSame(
            '<span class="chat-mention">@a&quot; onmouseover=&quot;x</span>',
            $this->renderer->render('&#64;[' . self::ALICE . ']', [self::ALICE => 'a" onmouseover="x']),
        );
    }

    public function test_tous_prints_itself_and_needs_no_lookup(): void
    {
        self::assertSame(
            '<span class="chat-mention">@tous</span> répète annulée',
            $this->renderer->render('&#64;[tous] répète annulée', []),
        );
    }

    public function test_a_member_with_no_mention_row_reads_as_unknown(): void
    {
        // Only legacy messages and corrupt data get here: the rows are written with the message.
        self::assertSame(
            '<span class="chat-mention">@inconnu</span>',
            $this->renderer->render('&#64;[' . self::ALICE . ']', []),
        );
    }

    public function test_a_uuid_in_capitals_resolves_to_the_same_member(): void
    {
        // The format is matched case-insensitively on both sides, so the lookup has to be too or the
        // same person written two ways would render as two different ones.
        self::assertSame(
            '<span class="chat-mention">@alice</span>',
            $this->renderer->render('&#64;[' . mb_strtoupper(self::ALICE) . ']', [self::ALICE => 'alice']),
        );
    }

    public function test_it_renders_every_mention_in_a_message(): void
    {
        self::assertSame(
            '<span class="chat-mention">@alice</span> et <span class="chat-mention">@bob</span>',
            $this->renderer->render(
                '&#64;[' . self::ALICE . '] et &#64;[' . self::BOB . ']',
                [self::ALICE => 'alice', self::BOB => 'bob'],
            ),
        );
    }

    public function test_it_leaves_content_with_no_mention_untouched(): void
    {
        $content = 'on répète mardi &lt;3<br />à 20h';
        self::assertSame($content, $this->renderer->render($content, []));
    }

    public function test_something_that_only_looks_like_a_mention_is_left_alone(): void
    {
        // A malformed id is text somebody typed, not a mention, and must not become a styled span.
        $content = 'écris à &#64;[pas-un-uuid] ou &#64;[] ou &#64;alice';
        self::assertSame($content, $this->renderer->render($content, []));
    }
}

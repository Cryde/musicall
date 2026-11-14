<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Profile\Wall;

use App\Entity\Profile\ProfilePostComment;
use App\Service\Profile\Wall\CommentCursor;
use App\Service\Profile\Wall\CommentOrder;
use App\Service\Profile\Wall\DatedPosition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommentCursorTest extends TestCase
{
    public function test_a_cursor_names_its_order_and_reads_back_what_it_wrote(): void
    {
        $comment = new ProfilePostComment();
        $comment->id = '01a1b220-df08-7910-9e2a-d78c147e10d0';
        $comment->creationDatetime = new \DateTimeImmutable('2026-10-05T10:00:00+00:00');

        $cursor = CommentCursor::of(CommentOrder::Recent, $comment);

        $this->assertSame('recent_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0', $cursor->toString());
        $this->assertEquals($cursor, CommentCursor::parse($cursor->toString()));
    }

    public function test_every_order_makes_a_cursor_the_pattern_takes(): void
    {
        $comment = new ProfilePostComment();
        $comment->id = '01a1b220-df08-7910-9e2a-d78c147e10d0';
        $comment->creationDatetime = new \DateTimeImmutable('2026-10-05T10:00:00+00:00');

        foreach (CommentOrder::cases() as $order) {
            $cursor = CommentCursor::of($order, $comment);

            $this->assertMatchesRegularExpression(CommentCursor::PATTERN, $cursor->toString());
            $this->assertSame($order, CommentCursor::parse($cursor->toString())?->order);
        }
    }

    public function test_a_most_liked_cursor_carries_the_count(): void
    {
        $comment = new ProfilePostComment();
        $comment->id = '01a1b220-df08-7910-9e2a-d78c147e10d0';
        $comment->creationDatetime = new \DateTimeImmutable('2026-10-05T10:00:00+00:00');
        $comment->likeCount = 12;

        $cursor = CommentCursor::of(CommentOrder::Liked, $comment);

        $this->assertSame('liked_12_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0', $cursor->toString());
        $this->assertEquals($cursor, CommentCursor::parse($cursor->toString()));
        $this->assertSame('recent_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0', CommentCursor::of(CommentOrder::Recent, $comment)->toString());
    }

    public function test_a_position_reads_back_what_it_wrote(): void
    {
        $position = DatedPosition::of(new \DateTimeImmutable('2026-10-05T10:00:00+00:00'), '01a1b220-df08-7910-9e2a-d78c147e10d0');

        $this->assertSame('1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0', $position->toString());
        $this->assertEquals($position, DatedPosition::parse($position->toString()));
    }

    #[DataProvider('notACommentCursor')]
    public function test_anything_else_is_refused(string $value): void
    {
        $this->assertNull(CommentCursor::parse($value));
    }

    /** @return iterable<string, array{string}> */
    public static function notACommentCursor(): iterable
    {
        yield 'a position without its order' => ['1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'an unknown order' => ['popular_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a trailing newline' => ["recent_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0\n"];
        yield 'a null byte' => ["recent_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0\0"];
        yield 'an int id' => ['recent_1791194400_42'];
        yield 'a most liked cursor without its count' => ['liked_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a count on another order' => ['recent_3_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'an overlong count' => ['liked_12345678901_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'an overlong timestamp' => ['recent_179119440012_01a1b220-df08-7910-9e2a-d78c147e10d0'];
    }

    #[DataProvider('notAPosition')]
    public function test_a_position_refuses_anything_else(string $value): void
    {
        $this->assertNull(DatedPosition::parse($value));
    }

    /** @return iterable<string, array{string}> */
    public static function notAPosition(): iterable
    {
        yield 'a comment cursor' => ['recent_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a trailing newline' => ["1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0\n"];
        yield 'a bare id' => ['01a1b220-df08-7910-9e2a-d78c147e10d0'];
    }
}

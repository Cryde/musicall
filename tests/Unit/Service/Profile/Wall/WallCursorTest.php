<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Profile\Wall;

use App\Service\Profile\Wall\WallCursor;
use App\Service\Profile\Wall\WallItemKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WallCursorTest extends TestCase
{
    public function test_a_cursor_reads_back_what_it_wrote(): void
    {
        $cursor = WallCursor::at(WallItemKind::Announce, new \DateTimeImmutable('2026-10-05T10:00:00+00:00'), '01a1b220-df08-7910-9e2a-d78c147e10d0');

        $this->assertSame('announce_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0', $cursor->toString());
        $this->assertEquals($cursor, WallCursor::parse($cursor->toString()));
        $this->assertEquals(WallCursor::at(WallItemKind::Publication, new \DateTimeImmutable('@1791194400'), '42'), WallCursor::parse('publication_1791194400_42'));
    }

    #[DataProvider('notACursor')]
    public function test_anything_else_is_refused(string $value): void
    {
        $this->assertNull(WallCursor::parse($value));
    }

    /** @return iterable<string, array{string}> */
    public static function notACursor(): iterable
    {
        yield 'a bare post id' => ['01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'an unknown kind' => ['comment_1791194400_42'];
        yield 'a trailing newline' => ["post_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0\n"];
        yield 'a null byte' => ["publication_1791194400_42\0"];
        yield 'a date instead of a timestamp' => ['post_2026-10-05_42'];
        yield 'a publication with a uuid' => ['publication_1791194400_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a post with an int id' => ['post_1791194400_42'];
        yield 'an overlong timestamp' => ['post_179119440012_01a1b220-df08-7910-9e2a-d78c147e10d0'];
    }

    public function test_the_order_is_date_then_kind_then_id_newest_first(): void
    {
        $at = static fn (WallItemKind $kind, int $timestamp, string $id): WallCursor => WallCursor::at($kind, new \DateTimeImmutable('@' . $timestamp), $id);
        $cursors = [
            $at(WallItemKind::TeacherMedia, 100, 'b'),
            $at(WallItemKind::Post, 100, 'a'),
            $at(WallItemKind::Publication, 100, '9'),
            $at(WallItemKind::Publication, 100, '10'),
            $at(WallItemKind::Post, 100, 'b'),
            $at(WallItemKind::Announce, 200, 'a'),
        ];

        usort($cursors, WallCursor::compare(...));

        $this->assertSame(
            ['announce_200_a', 'post_100_b', 'post_100_a', 'publication_100_10', 'publication_100_9', 'teacher_media_100_b'],
            array_map(static fn (WallCursor $cursor): string => $cursor->toString(), $cursors),
        );
    }
}

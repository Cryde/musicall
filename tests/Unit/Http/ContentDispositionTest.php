<?php declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\ContentDisposition;
use PHPUnit\Framework\TestCase;

class ContentDispositionTest extends TestCase
{
    public function test_plain_ascii_filename_is_unchanged(): void
    {
        // Already-safe names keep their exact filename and emit no filename*.
        self::assertSame(
            'attachment; filename=doc.txt',
            ContentDisposition::attachment('doc.txt'),
        );
    }

    public function test_accented_name_gets_ascii_fallback_and_rfc5987_name(): void
    {
        self::assertSame(
            "attachment; filename=Repetition.pdf; filename*=utf-8''R%C3%A9p%C3%A9tition.pdf",
            ContentDisposition::attachment('Répétition.pdf'),
        );
    }

    public function test_slash_is_replaced_and_does_not_throw(): void
    {
        self::assertSame(
            'attachment; filename=Rock-Metal.pdf',
            ContentDisposition::attachment('Rock/Metal.pdf'),
        );
    }

    public function test_spaces_are_slugged_in_the_fallback(): void
    {
        self::assertSame(
            "attachment; filename=Live-2026.pdf; filename*=utf-8''Live%202026.pdf",
            ContentDisposition::attachment('Live 2026.pdf'),
        );
    }

    public function test_internal_dots_preserve_the_extension(): void
    {
        self::assertSame(
            'attachment; filename=my.file.v2.txt',
            ContentDisposition::attachment('my.file.v2.txt'),
        );
    }

    public function test_name_that_slugs_to_nothing_falls_back_to_file(): void
    {
        self::assertSame(
            "attachment; filename=file; filename*=utf-8''%21%21%21",
            ContentDisposition::attachment('!!!'),
        );
    }
}

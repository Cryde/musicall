<?php declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\DurationExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The format table the PDF prints. It has to agree, value for value, with formatDuration() in
 * assets/js/utils/setlistDuration.js: the point of having one format is that the total on a printed
 * sheet reads exactly like the total in the editor.
 */
class DurationExtensionTest extends TestCase
{
    /** @return iterable<string, array{int|null, string}> */
    public static function durationProvider(): iterable
    {
        yield 'under a minute keeps an explicit zero minute' => [47, '0:47'];
        yield 'seconds are always two digits' => [7, '0:07'];
        yield 'a song reads as minutes and seconds' => [227, '3:47'];
        yield 'a padded second is not lost' => [187, '3:07'];
        yield 'a whole minute' => [600, '10:00'];
        // The set total the header used to print as "97 min 30 s".
        yield 'a full set rolls over to hours' => [5850, '1:37:30'];
        yield 'exactly one hour' => [3600, '1:00:00'];
        yield 'one hour and change pads both fields' => [3661, '1:01:01'];
        yield 'the API ceiling of 24 hours' => [86400, '24:00:00'];
        yield 'a real zero still reads as a duration' => [0, '0:00'];
        yield 'an absent duration reads as nothing' => [null, ''];
        yield 'a negative duration reads as nothing rather than a minus sign' => [-1, ''];
    }

    #[DataProvider('durationProvider')]
    public function test_format(?int $seconds, string $expected): void
    {
        $this->assertSame($expected, DurationExtension::format($seconds));
    }
}

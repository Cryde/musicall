<?php declare(strict_types=1);

namespace App\Tests\Unit\Exception\BandSpace;

use App\Exception\BandSpace\QuotaExceededException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The message is the product here, not a debug string: it is the whole of what a member is told when an
 * upload, an attachment or a restore is refused, so its wording and its units are asserted exactly.
 */
class QuotaExceededExceptionTest extends TestCase
{
    /**
     * Each row crosses one 1024 boundary, and every figure is asserted whole rather than by substring,
     * because a unit or a decimal drifting from formatBytes.js is exactly the regression that matters:
     * this sentence is read next to a quota bar that formats the same numbers with that helper.
     */
    #[DataProvider('provideRefusals')]
    public function test_every_size_is_written_in_french_units(int $quotaBytes, int $usedBytes, int $incomingBytes, string $expected): void
    {
        $exception = new QuotaExceededException($quotaBytes, $usedBytes, $incomingBytes);

        self::assertSame($expected, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function provideRefusals(): iterable
    {
        yield 'bytes stay whole' => [
            1000, 900, 900,
            'Quota de stockage dépassé : 900 o utilisés sur 1000 o autorisés, il manque 800 o pour ajouter 900 o.',
        ];
        yield 'a kilobyte quota against byte-sized files' => [
            1024, 1000, 1000,
            'Quota de stockage dépassé : 1000 o utilisés sur 1.0 Ko autorisés, il manque 976 o pour ajouter 1000 o.',
        ];
        yield 'kilobytes keep one decimal' => [
            1_048_576, 1_048_000, 2048,
            'Quota de stockage dépassé : 1023.4 Ko utilisés sur 1.0 Mo autorisés, il manque 1.4 Ko pour ajouter 2.0 Ko.',
        ];
        yield 'megabytes keep one decimal, gigabytes two' => [
            1_073_741_824, 1_073_000_000, 2_097_152,
            'Quota de stockage dépassé : 1023.3 Mo utilisés sur 1.00 Go autorisés, il manque 1.3 Mo pour ajouter 2.0 Mo.',
        ];
        // The shipped default quota, within 12 Mo of its limit: the case the raw byte counts made unreadable.
        yield 'the real five gigabyte quota' => [
            5_368_709_120, 5_363_000_000, 12_345_678,
            'Quota de stockage dépassé : 4.99 Go utilisés sur 5.00 Go autorisés, il manque 6.3 Mo pour ajouter 11.8 Mo.',
        ];
        // An exact decimal tie, which is where sprintf('%.1f') and the JavaScript toFixed part company:
        // sprintf rounds half to even and would print 1.2 Ko for these, the quota bar beside it 1.3 Ko.
        // Any multiple of 256 bytes lands on a tie, and a used total is a sum of arbitrary file sizes.
        yield 'a kilobyte tie rounds half up, as toFixed does' => [
            1024, 1280, 1280,
            'Quota de stockage dépassé : 1.3 Ko utilisés sur 1.0 Ko autorisés, il manque 1.5 Ko pour ajouter 1.3 Ko.',
        ];
        yield 'a megabyte tie rounds half up, as toFixed does' => [
            1_048_576, 1_310_720, 1_310_720,
            'Quota de stockage dépassé : 1.3 Mo utilisés sur 1.0 Mo autorisés, il manque 1.5 Mo pour ajouter 1.3 Mo.',
        ];
        yield 'a gigabyte tie rounds half up on the second decimal' => [
            1_073_741_824, 1_207_959_552, 1_207_959_552,
            'Quota de stockage dépassé : 1.13 Go utilisés sur 1.00 Go autorisés, il manque 1.25 Go pour ajouter 1.13 Go.',
        ];
    }

    public function test_no_raw_byte_count_survives_in_the_message(): void
    {
        $message = (new QuotaExceededException(5_368_709_120, 5_363_000_000, 12_345_678))->getMessage();

        self::assertStringNotContainsString('octets', $message);
        self::assertStringNotContainsString('5363000000', $message);
        self::assertStringNotContainsString('12345678', $message);
        self::assertStringNotContainsString('5368709120', $message);
    }

    public function test_it_is_still_an_unprocessable_entity(): void
    {
        $exception = new QuotaExceededException(1000, 900, 900);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
    }
}

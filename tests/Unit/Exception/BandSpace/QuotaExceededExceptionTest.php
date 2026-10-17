<?php declare(strict_types=1);

namespace App\Tests\Unit\Exception\BandSpace;

use App\Exception\BandSpace\QuotaExceededException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

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

        self::assertSame('band_space.storage.quota_exceeded', $exception->getMessage());
        self::assertSame($expected, $exception->trans(self::translator()));
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

    /**
     * The same figures in the units the English quota bar writes them in: formatBytes.js picks its
     * units by locale, so the sentence beside it has to follow.
     */
    #[DataProvider('provideEnglishRefusals')]
    public function test_every_size_is_written_in_english_units_for_an_english_reader(int $quotaBytes, int $usedBytes, int $incomingBytes, string $expected): void
    {
        $exception = new QuotaExceededException($quotaBytes, $usedBytes, $incomingBytes);

        self::assertSame($expected, $exception->trans(self::translator('en')));
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function provideEnglishRefusals(): iterable
    {
        yield 'bytes stay whole' => [
            1000, 900, 900,
            'Storage quota exceeded: 900 B used out of 1000 B allowed, 800 B more is needed to add 900 B.',
        ];
        yield 'kilobytes keep one decimal' => [
            1_048_576, 1_048_000, 2048,
            'Storage quota exceeded: 1023.4 KB used out of 1.0 MB allowed, 1.4 KB more is needed to add 2.0 KB.',
        ];
        yield 'the real five gigabyte quota' => [
            5_368_709_120, 5_363_000_000, 12_345_678,
            'Storage quota exceeded: 4.99 GB used out of 5.00 GB allowed, 6.3 MB more is needed to add 11.8 MB.',
        ];
    }

    public function test_a_locale_asked_for_wins_over_the_translator_locale(): void
    {
        $exception = new QuotaExceededException(1000, 900, 900);

        self::assertSame(
            'Storage quota exceeded: 900 B used out of 1000 B allowed, 800 B more is needed to add 900 B.',
            $exception->trans(self::translator('fr'), 'en'),
        );
        self::assertSame(
            'Quota de stockage dépassé : 900 o utilisés sur 1000 o autorisés, il manque 800 o pour ajouter 900 o.',
            $exception->trans(self::translator('en'), 'fr'),
        );
    }

    public function test_the_parameters_alone_are_in_french_units(): void
    {
        $exception = new QuotaExceededException(1_048_576, 1_048_000, 2048);

        self::assertSame(
            ['used' => '1023.4 Ko', 'quota' => '1.0 Mo', 'missing' => '1.4 Ko', 'incoming' => '2.0 Ko'],
            $exception->getParameters(),
        );
    }

    public function test_no_raw_byte_count_survives_in_the_message(): void
    {
        $message = (new QuotaExceededException(5_368_709_120, 5_363_000_000, 12_345_678))->trans(self::translator());

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

    /**
     * The real catalogues, so the sentences asserted above are the ones a member reads.
     */
    private static function translator(string $locale = 'fr'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['fr', 'en'] as $catalogueLocale) {
            $translator->addResource(
                'yaml',
                __DIR__ . '/../../../../translations/messages+intl-icu.' . $catalogueLocale . '.yaml',
                $catalogueLocale,
                'messages+intl-icu',
            );
        }

        return $translator;
    }
}

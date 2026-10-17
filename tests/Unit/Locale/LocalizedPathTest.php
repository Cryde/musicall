<?php

declare(strict_types=1);

namespace App\Tests\Unit\Locale;

use App\Locale\LocalizedPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reads the cases the router's test reads (assets/js/i18n/localizedPath.test.js), so the URLs the server names in
 * hreflang links are the ones the router serves.
 */
class LocalizedPathTest extends TestCase
{
    private LocalizedPath $localizedPath;

    protected function setUp(): void
    {
        $this->localizedPath = new LocalizedPath(['fr', 'en'], 'fr');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localeOfCases(): iterable
    {
        foreach (self::cases()['locale_of'] as $case) {
            yield $case['path'] => [$case['path'], $case['locale']];
        }
    }

    #[DataProvider('localeOfCases')]
    public function test_the_locale_of_a_path(string $path, string $locale): void
    {
        self::assertSame($locale, $this->localizedPath->localeOf($path));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function inLocaleCases(): iterable
    {
        foreach (self::cases()['in_locale'] as $case) {
            yield $case['path'] . ' in ' . $case['locale'] => [$case['path'], $case['locale'], $case['expected']];
        }
    }

    #[DataProvider('inLocaleCases')]
    public function test_the_path_of_a_page_in_a_locale(string $path, string $locale, string $expected): void
    {
        self::assertSame($expected, $this->localizedPath->inLocale($path, $locale));
    }

    public function test_a_path_without_prefix_names_no_locale(): void
    {
        self::assertNull($this->localizedPath->prefixLocale('/publications'));
        self::assertSame('en', $this->localizedPath->prefixLocale('/en/publications'));
    }

    public function test_no_prefix_exists_while_french_is_the_only_enabled_locale(): void
    {
        $frenchOnly = new LocalizedPath(['fr'], 'fr');

        self::assertNull($frenchOnly->prefixLocale('/en/publications'));
        self::assertSame('fr', $frenchOnly->localeOf('/en/publications'));
        self::assertSame('/en/publications', $frenchOnly->inLocale('/en/publications', 'fr'));
        self::assertSame('/lessons', $frenchOnly->inDefaultLocale('/lessons'));
        self::assertSame('/find-a-guitarist', $frenchOnly->inDefaultLocale('/find-a-guitarist'));
        self::assertSame('/u/jeanne/teacher/', $frenchOnly->inDefaultLocale('/u/jeanne/teacher/'));
        self::assertSame('//publications', $frenchOnly->inDefaultLocale('//publications'));
    }

    /**
     * @return array{locale_of: list<array{path: string, locale: string}>, in_locale: list<array{path: string, locale: string, expected: string}>}
     */
    private static function cases(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/Locale/localized_paths.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}

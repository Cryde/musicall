<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation;

use App\Entity\Attribute\Instrument;
use App\Translation\ReferenceNameSorter;
use App\Translation\ReferenceNameTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class ReferenceNameSorterTest extends TestCase
{
    public function test_french_keeps_the_database_order(): void
    {
        self::assertSame(['Guitare', 'Chant'], self::sorter('fr')->sort(['Guitare', 'Chant'], self::itself(...)));
    }

    /** An accented letter sorts with its base letter, not after z as a byte comparison would put it. */
    public function test_another_locale_sorts_by_its_own_collation(): void
    {
        self::assertSame(['echo', 'Élan', 'fox'], self::sorter('en')->sort(['fox', 'Élan', 'echo'], self::itself(...)));
    }

    public function test_the_records_follow_their_names(): void
    {
        $records = [['name' => 'Vocals'], ['name' => 'Guitar']];

        self::assertSame(
            [['name' => 'Guitar'], ['name' => 'Vocals']],
            self::sorter('en')->sort($records, static fn (array $record): string => $record['name']),
        );
    }

    /**
     * Two French names can share one translation: the database order decides between them. Forty, because
     * Collator::asort() happens to keep a handful of ties in place and only scrambles a longer run.
     */
    public function test_records_sharing_a_name_keep_the_database_order(): void
    {
        $records = array_map(static fn (int $id): array => ['id' => $id, 'name' => 'Pop'], range(1, 40));
        $records[] = ['id' => 0, 'name' => 'Banjo'];

        self::assertSame(
            range(0, 40),
            array_column(self::sorter('en')->sort($records, static fn (array $record): string => $record['name']), 'id'),
        );
    }

    public function test_instrument_names_come_translated_and_in_the_request_locale_order(): void
    {
        $sorter = self::sorter('en', ['instrument.chant' => 'Vocals', 'instrument.guitare' => 'Guitar']);

        self::assertSame(
            [['id' => 'id-guitare', 'name' => 'Guitar'], ['id' => 'id-chant', 'name' => 'Vocals']],
            $sorter->instrumentNames([self::instrument('chant', 'Chant'), self::instrument('guitare', 'Guitare')]),
        );
    }

    /**
     * @param array<string, string> $names
     */
    private static function sorter(string $locale, array $names = []): ReferenceNameSorter
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $names, $locale, 'reference');

        return new ReferenceNameSorter($translator, new ReferenceNameTranslator($translator));
    }

    private static function instrument(string $slug, string $name): Instrument
    {
        $instrument = new Instrument();
        $instrument->id = 'id-' . $slug;
        $instrument->slug = $slug;
        $instrument->name = $name;

        return $instrument;
    }

    private static function itself(string $name): string
    {
        return $name;
    }
}

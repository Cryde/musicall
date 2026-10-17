<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\PublicationSubCategory;
use App\Translation\ReferenceNameTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class ReferenceNameTranslatorTest extends TestCase
{
    public function test_french_reads_the_database_name(): void
    {
        $names = $this->namesIn('fr', self::guitar(), self::rock(), self::news());

        self::assertSame(['Guitare', 'Guitariste', 'Rock', 'Actualités'], $names);
    }

    public function test_another_language_reads_the_catalogue_by_slug(): void
    {
        $names = $this->namesIn('en', self::guitar(), self::rock(), self::news());

        self::assertSame(['Guitar', 'Guitarist', 'Rock music', 'News'], $names);
    }

    public function test_another_language_without_an_entry_falls_back_to_the_database_name(): void
    {
        $instrument = new Instrument();
        $instrument->name = 'Ukulélé';
        $instrument->musicianName = 'Joueur / Joueuse de Ukulélé';
        $instrument->slug = 'ukulele';
        $style = new Style();
        $style->name = 'Zouk';
        $style->slug = 'zouk';
        $subCategory = new PublicationSubCategory();
        $subCategory->title = 'Chroniques';
        $subCategory->slug = 'chroniques';

        $names = $this->namesIn('en', $instrument, $style, $subCategory);

        self::assertSame(['Ukulélé', 'Joueur / Joueuse de Ukulélé', 'Zouk', 'Chroniques'], $names);
    }

    /**
     * @return list<string>
     */
    private function namesIn(string $locale, Instrument $instrument, Style $style, PublicationSubCategory $subCategory): array
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['fr']);
        $translator->addLoader('array', new ArrayLoader());
        // French has no name in the catalogue, only the other reference labels.
        $translator->addResource('array', ['skill_level.beginner' => 'Débutant'], 'fr', 'reference+intl-icu');
        $translator->addResource('array', [
            'instrument.guitare' => 'Guitar',
            'instrument_musician.guitare' => 'Guitarist',
            'style.rock' => 'Rock music',
            'publication_sub_category.actualites' => 'News',
        ], 'en', 'reference+intl-icu');

        $names = new ReferenceNameTranslator($translator);

        return [
            $names->instrument($instrument),
            $names->instrumentMusicianName($instrument),
            $names->style($style),
            $names->subCategory($subCategory),
        ];
    }

    private static function guitar(): Instrument
    {
        $instrument = new Instrument();
        $instrument->name = 'Guitare';
        $instrument->musicianName = 'Guitariste';
        $instrument->slug = 'guitare';

        return $instrument;
    }

    private static function rock(): Style
    {
        $style = new Style();
        $style->name = 'Rock';
        $style->slug = 'rock';

        return $style;
    }

    private static function news(): PublicationSubCategory
    {
        $subCategory = new PublicationSubCategory();
        $subCategory->title = 'Actualités';
        $subCategory->slug = 'actualites';

        return $subCategory;
    }
}

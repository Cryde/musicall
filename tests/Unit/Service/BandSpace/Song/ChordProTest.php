<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\Song;

use App\Service\BandSpace\Song\ChordPro\ChordProParser;
use App\Service\BandSpace\Song\ChordPro\ChordTransposer;
use App\Service\BandSpace\Song\ChordPro\SingerPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the cases assets/js/utils/chordpro.test.js runs, so the PDF reads a song the way the drawer does.
 */
class ChordProTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_it_reads_a_sheet_like_the_front_end(string $source, mixed $expected): void
    {
        $this->assertSame($expected, (new ChordProParser())->sheet($source));
    }

    #[DataProvider('singerCases')]
    public function test_it_lists_the_singers_like_the_front_end(string $source, mixed $expected): void
    {
        $this->assertSame($expected, (new ChordProParser())->singerIds($source));
    }

    #[DataProvider('chordCases')]
    public function test_it_transposes_a_chord_like_the_front_end(string $chord, int $steps, bool $flats, string $expected): void
    {
        $this->assertSame($expected, (new ChordTransposer())->transposeChord($chord, $steps, $flats));
    }

    #[DataProvider('keyCases')]
    public function test_it_transposes_a_key_like_the_front_end(?string $key, int $steps, ?string $expected): void
    {
        $this->assertSame($expected, (new ChordTransposer())->transposeKey($key, $steps));
    }

    #[DataProvider('sourceCases')]
    public function test_it_transposes_a_source_like_the_front_end(string $source, int $steps, ?string $key, string $expected): void
    {
        $this->assertSame($expected, (new ChordTransposer())->transposeSource($source, $steps, $key));
    }

    public function test_it_colours_singers_from_the_shared_palette(): void
    {
        $palette = self::fixture()['palette'];

        $this->assertSame($palette['singers'], SingerPalette::SINGERS);
        $this->assertSame($palette['all'], SingerPalette::ALL);
    }

    public function test_it_knows_the_same_parts_as_the_front_end(): void
    {
        $kinds = [];
        foreach (self::fixture()['section_kinds'] as $kind) {
            $kinds[$kind['value']] = $kind['label'];
        }

        $this->assertSame($kinds, ChordProParser::SECTION_KINDS);
    }

    public function test_it_reads_nothing_from_no_lyrics(): void
    {
        $this->assertSame([], (new ChordProParser())->singerIds(null));
    }

    /** @return iterable<array{string, mixed}> */
    public static function cases(): iterable
    {
        foreach (self::fixture()['sheets'] as $case) {
            yield [$case['source'], $case['expected']];
        }
    }

    /** @return iterable<array{string, mixed}> */
    public static function singerCases(): iterable
    {
        foreach (self::fixture()['singers'] as $case) {
            yield [$case['source'], $case['expected']];
        }
    }

    /** @return iterable<array{string, int, bool, string}> */
    public static function chordCases(): iterable
    {
        foreach (self::fixture()['chords'] as $case) {
            yield $case['chord'] . ' ' . $case['steps'] => [$case['chord'], $case['steps'], $case['flats'], $case['expected']];
        }
    }

    /** @return iterable<array{?string, int, ?string}> */
    public static function keyCases(): iterable
    {
        foreach (self::fixture()['keys'] as $case) {
            yield [$case['key'], $case['steps'], $case['expected']];
        }
    }

    /** @return iterable<array{string, int, ?string, string}> */
    public static function sourceCases(): iterable
    {
        foreach (self::fixture()['sources'] as $case) {
            yield [$case['source'], $case['steps'], $case['key'], $case['expected']];
        }
    }

    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../../../Fixtures/ChordPro/cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}

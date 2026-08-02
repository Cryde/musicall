<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\BandSpace;

use App\Enum\BandSpace\TechRiderColour;
use PHPUnit\Framework\TestCase;

/**
 * The rider palette is defined twice: once as a PHP enum, because the export renderer needs the
 * hex, and once in assets/js/constants/techRiderColours.js, because the text editor toolbar
 * paints swatches and its paste sanitiser has to recognise a pasted colour synchronously.
 *
 * #768 originally called for an endpoint so there would be one definition. That was reconsidered
 * and deliberately not built: the palette is a PHP enum, so changing it is a deploy, and the
 * frontend bundle rebuilds in the same deploy. An endpoint would therefore buy nothing at runtime
 * while making the paste sanitiser depend on a request having completed, and a save arriving
 * before the palette loaded would silently strip every colour from the document.
 *
 * So the two definitions stay, and this test is the contract between them. Drift is the failure
 * mode that mattered, and drift fails here.
 */
class TechRiderColourPaletteTest extends TestCase
{
    private const string PALETTE_PATH = 'assets/js/constants/techRiderColours.js';

    public function test_the_php_enum_and_the_javascript_palette_hold_the_same_colours(): void
    {
        $this->assertSame(
            array_map(
                static fn (TechRiderColour $colour): array => [
                    'value' => $colour->value,
                    'label' => $colour->label(),
                    'hex' => $colour->hex(),
                ],
                TechRiderColour::cases(),
            ),
            $this->paletteFromJavaScript(),
            sprintf(
                "The rider palette has drifted. Update %s and %s together, in the same order.",
                self::PALETTE_PATH,
                TechRiderColour::class,
            ),
        );
    }

    /**
     * Lowercase hex is not cosmetic: the editor's paste sanitiser lowercases before comparing,
     * so an uppercase value in either definition would silently fail to match.
     */
    public function test_every_hex_is_a_lowercase_six_digit_value(): void
    {
        foreach (TechRiderColour::cases() as $colour) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colour->hex(), $colour->value);
        }
    }

    /**
     * @return list<array{value: string, label: string, hex: string}>
     */
    private function paletteFromJavaScript(): array
    {
        // tests/Unit/Enum/BandSpace -> project root
        $path = \dirname(__DIR__, 4) . '/' . self::PALETTE_PATH;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        preg_match_all(
            "/\{\s*value:\s*'([^']+)',\s*label:\s*'([^']+)',\s*hex:\s*'([^']+)'\s*}/",
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        // Guards against the regex silently matching nothing, which would turn the comparison
        // above into "the enum differs from an empty list" instead of a drift report.
        self::assertNotEmpty($matches, sprintf('No colour entries were parsed out of %s.', self::PALETTE_PATH));

        return array_map(
            static fn (array $match): array => ['value' => $match[1], 'label' => $match[2], 'hex' => $match[3]],
            $matches,
        );
    }
}

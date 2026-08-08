<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\BandSpace;

use App\Enum\BandSpace\TechRiderStagePlotIcon;
use PHPUnit\Framework\TestCase;

/**
 * An icon is two halves that live apart: a case in the enum and a PNG on disk. Adding one and
 * forgetting the other produces a picker entry with a broken image, or a file nobody can reach,
 * and neither shows up until somebody looks at the screen.
 *
 * This is the seam, so this is where it is checked.
 */
class TechRiderStagePlotIconArtworkTest extends TestCase
{
    public function test_every_icon_has_a_file_on_disk(): void
    {
        $missing = [];
        foreach (TechRiderStagePlotIcon::cases() as $icon) {
            if (!is_file($this->publicPath($icon->imagePath()))) {
                $missing[] = $icon->value;
            }
        }

        self::assertSame([], $missing, sprintf(
            "These icons have no artwork. Add {slug}.png to public/%s.",
            TechRiderStagePlotIcon::IMAGE_DIRECTORY,
        ));
    }

    /** The other direction: a file with no case is unreachable and should be deleted or declared. */
    public function test_every_file_on_disk_has_an_icon(): void
    {
        $directory = \dirname(__DIR__, 4) . '/public/' . TechRiderStagePlotIcon::IMAGE_DIRECTORY;
        self::assertDirectoryExists($directory);

        $files = glob($directory . '/*.png');
        self::assertIsArray($files);
        // Guards against the glob matching nothing, which would make this pass vacuously.
        self::assertNotEmpty($files);

        $slugs = TechRiderStagePlotIcon::values();
        $orphans = [];
        foreach ($files as $file) {
            $slug = basename($file, '.png');
            if (!in_array($slug, $slugs, true)) {
                $orphans[] = $slug;
            }
        }

        self::assertSame([], $orphans, 'These files have no matching TechRiderStagePlotIcon case.');
    }

    /** A zero byte file passes an existence check and renders as nothing. */
    public function test_every_file_is_a_real_png(): void
    {
        foreach (TechRiderStagePlotIcon::cases() as $icon) {
            $path = $this->publicPath($icon->imagePath());
            $size = getimagesize($path);

            self::assertIsArray($size, sprintf('%s is not a readable image.', $icon->value));
            self::assertSame(IMAGETYPE_PNG, $size[2], sprintf('%s is not a PNG.', $icon->value));
        }
    }

    /** Same seam as the PNGs, for the half of the set that has real artwork. */
    public function test_every_declared_symbol_has_a_file_on_disk(): void
    {
        $missing = [];
        foreach (TechRiderStagePlotIcon::cases() as $icon) {
            $path = $icon->symbolPath();
            if ($path !== null && !is_file($this->projectPath($path))) {
                $missing[] = $icon->value;
            }
        }

        self::assertSame([], $missing, sprintf(
            'These icons declare a symbol with no file. Add {slug}.svg to %s.',
            TechRiderStagePlotIcon::SYMBOL_DIRECTORY,
        ));
    }

    /** The other direction: a drawn symbol nothing declares is invisible, so it looks like a bug. */
    public function test_every_symbol_file_on_disk_is_declared(): void
    {
        $files = glob($this->projectPath(TechRiderStagePlotIcon::SYMBOL_DIRECTORY) . '/*.svg');
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        $declared = [];
        foreach (TechRiderStagePlotIcon::cases() as $icon) {
            if ($icon->symbolPath() !== null) {
                $declared[] = $icon->value;
            }
        }

        $orphans = [];
        foreach ($files as $file) {
            $slug = basename($file, '.svg');
            if (!in_array($slug, $declared, true)) {
                $orphans[] = $slug;
            }
        }

        self::assertSame([], $orphans, sprintf(
            'These symbol files are not declared by any case, so nothing renders them. Add them to %s::symbolPath().',
            TechRiderStagePlotIcon::class,
        ));
    }

    /**
     * Both failures are silent otherwise: a hardcoded colour just ignores its category, and a
     * literal stroke-width opts that one file out of the calibration constant.
     */
    public function test_every_symbol_takes_its_colour_and_stroke_from_the_page(): void
    {
        foreach (TechRiderStagePlotIcon::cases() as $icon) {
            $path = $icon->symbolPath();
            if ($path === null) {
                continue;
            }

            $markup = file_get_contents($this->projectPath($path));
            self::assertIsString($markup);

            self::assertStringContainsString('currentColor', $markup, sprintf(
                '%s must take its colour from the page.',
                $icon->value,
            ));
            self::assertDoesNotMatchRegularExpression(
                '/(#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\()/i',
                $markup,
                sprintf('%s hardcodes a colour, so it cannot follow its category.', $icon->value),
            );
            self::assertDoesNotMatchRegularExpression(
                '/stroke-width\s*=\s*"/',
                $markup,
                sprintf(
                    '%s sets stroke-width as an attribute, which var() cannot reach. Use style="stroke-width:var(--sp-stroke, 2)".',
                    $icon->value,
                ),
            );
            self::assertStringContainsString('var(--sp-stroke', $markup, sprintf(
                '%s does not follow the calibration constant.',
                $icon->value,
            ));
        }
    }

    /**
     * Callers size an icon by width alone and let height follow, which is only safe while every icon
     * is square. A tall one would overflow the fixed box the picker and the legend give it.
     */
    public function test_every_icon_is_square(): void
    {
        foreach (TechRiderStagePlotIcon::cases() as $icon) {
            [$width, $height] = getimagesize($this->publicPath($icon->imagePath()));
            self::assertSame($width, $height, sprintf('%s.png is not square.', $icon->value));

            $path = $icon->symbolPath();
            if ($path === null) {
                continue;
            }

            $markup = file_get_contents($this->projectPath($path));
            self::assertIsString($markup);
            self::assertMatchesRegularExpression(
                '/viewBox="0 0 (\\d+(?:\\.\\d+)?) \\1"/',
                $markup,
                sprintf('%s.svg must have a square viewBox.', $icon->value),
            );
        }
    }

    public function test_every_icon_has_a_distinct_label(): void
    {
        $labels = array_map(
            static fn (TechRiderStagePlotIcon $icon): string => $icon->label(),
            TechRiderStagePlotIcon::cases(),
        );

        // Two icons sharing a label are indistinguishable in the picker's list.
        self::assertSame(array_unique($labels), $labels);
    }

    private function publicPath(string $imagePath): string
    {
        return $this->projectPath('public' . $imagePath);
    }

    private function projectPath(string $relativePath): string
    {
        // tests/Unit/Enum/BandSpace -> project root
        return \dirname(__DIR__, 4) . '/' . $relativePath;
    }
}

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
        // tests/Unit/Enum/BandSpace -> project root
        return \dirname(__DIR__, 4) . '/public' . $imagePath;
    }
}

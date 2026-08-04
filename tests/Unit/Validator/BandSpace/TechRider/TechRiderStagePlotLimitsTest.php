<?php declare(strict_types=1);

namespace App\Tests\Unit\Validator\BandSpace\TechRider;

use App\Validator\BandSpace\TechRider\TechRiderStagePlot;
use PHPUnit\Framework\TestCase;

/**
 * The stage plot editor repeats the server's limits so a control can refuse a value where the user
 * is looking rather than after a round trip: the element cap greys out placement, the scale range
 * bounds a slider, the rotations fill a button group.
 *
 * Duplicated on purpose, for the same reason as the colour palette and the patch list limits: the
 * numbers are PHP constants, so changing them is a deploy and the bundle rebuilds in the same
 * deploy, which makes an endpoint pure overhead. Drift is the risk, and drift is what this pins.
 */
class TechRiderStagePlotLimitsTest extends TestCase
{
    private const string CONSTANTS_PATH = 'assets/js/constants/stagePlot.js';

    /**
     * @return array<string, int|float>
     */
    public static function expectedNumbers(): array
    {
        return [
            'STAGE_PLOT_SCHEMA_VERSION' => TechRiderStagePlot::SCHEMA_VERSION,
            'MAX_ELEMENTS' => TechRiderStagePlot::MAX_ELEMENTS,
            'MAX_LEGEND_ENTRIES' => TechRiderStagePlot::MAX_LEGEND_ENTRIES,
            'MAX_LABEL_LENGTH' => TechRiderStagePlot::MAX_LABEL_LENGTH,
            'MIN_SCALE' => TechRiderStagePlot::MIN_SCALE,
            'MAX_SCALE' => TechRiderStagePlot::MAX_SCALE,
            'MIN_ASPECT_RATIO' => TechRiderStagePlot::MIN_ASPECT_RATIO,
            'MAX_ASPECT_RATIO' => TechRiderStagePlot::MAX_ASPECT_RATIO,
        ];
    }

    public function test_the_editor_limits_match_the_constraint(): void
    {
        $source = $this->read();

        $found = [];
        foreach (array_keys(self::expectedNumbers()) as $name) {
            preg_match('/export const ' . $name . ' = ([0-9.]+)/', $source, $matches);
            // Guards against a renamed export silently comparing against zero and reporting a
            // drift that is really a rename.
            self::assertNotEmpty($matches, sprintf('%s is not exported from %s.', $name, self::CONSTANTS_PATH));
            $found[$name] = $matches[1] + 0;
        }

        $this->assertEquals(
            self::expectedNumbers(),
            $found,
            sprintf('The stage plot limits have drifted. Update %s and %s together.', self::CONSTANTS_PATH, TechRiderStagePlot::class),
        );
    }

    /**
     * The editor offers rotations as a button group, so an extra entry there would be a button that
     * saves and then fails validation.
     */
    public function test_the_editor_rotations_match_the_constraint(): void
    {
        preg_match('/export const ROTATIONS = Object\.freeze\(\[([^\]]+)]\)/', $this->read(), $matches);
        self::assertNotEmpty($matches, sprintf('No ROTATIONS export found in %s.', self::CONSTANTS_PATH));

        $rotations = array_map('intval', array_map('trim', explode(',', $matches[1])));

        $this->assertSame(TechRiderStagePlot::ALLOWED_ROTATIONS, $rotations);
    }

    /** The default has to be inside the range the server accepts, or a new plot cannot be saved. */
    public function test_the_default_aspect_ratio_is_within_range(): void
    {
        preg_match('/export const DEFAULT_ASPECT_RATIO = ([0-9.]+)/', $this->read(), $matches);
        self::assertNotEmpty($matches);

        $default = $matches[1] + 0;
        $this->assertGreaterThanOrEqual(TechRiderStagePlot::MIN_ASPECT_RATIO, $default);
        $this->assertLessThanOrEqual(TechRiderStagePlot::MAX_ASPECT_RATIO, $default);
    }

    /**
     * Every ratio the editor offers has to be inside the range the server accepts, or a user picks
     * one from a dropdown and the save is refused. Narrowing the range on the PHP side without
     * pruning the list is the drift this catches.
     */
    public function test_every_offered_aspect_ratio_is_within_range(): void
    {
        preg_match_all(
            '/\{ value: ([0-9.]+), label: /',
            $this->readEditor(),
            $matches,
        );
        self::assertNotEmpty($matches[1], 'No ASPECT_RATIO_OPTIONS entries were parsed.');

        foreach ($matches[1] as $value) {
            $ratio = $value + 0;
            self::assertGreaterThanOrEqual(TechRiderStagePlot::MIN_ASPECT_RATIO, $ratio, (string) $value);
            self::assertLessThanOrEqual(TechRiderStagePlot::MAX_ASPECT_RATIO, $ratio, (string) $value);
        }
    }

    private function readEditor(): string
    {
        // tests/Unit/Validator/BandSpace/TechRider -> project root
        $path = \dirname(__DIR__, 5) . '/assets/js/components/BandSpace/TechRider/RiderStagePlotEditor.vue';
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function read(): string
    {
        // tests/Unit/Validator/BandSpace/TechRider -> project root
        $path = \dirname(__DIR__, 5) . '/' . self::CONSTANTS_PATH;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}

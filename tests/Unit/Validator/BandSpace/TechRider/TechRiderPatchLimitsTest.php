<?php declare(strict_types=1);

namespace App\Tests\Unit\Validator\BandSpace\TechRider;

use App\Validator\BandSpace\TechRider\TechRiderPatchRows;
use PHPUnit\Framework\TestCase;

/**
 * The patch list editor repeats the server's limits: the row cap so it can grey out "Ajouter" and
 * show a count, and the field lengths so a long paste stops at the field rather than coming back
 * as a rejected save of the whole grid.
 *
 * That is a deliberate duplication, for the same reason as the colour palette in
 * TechRiderColourPaletteTest: the numbers are PHP constants, so changing them is a deploy and the
 * bundle rebuilds in the same deploy, which makes an endpoint pure overhead. What the duplication
 * does risk is drift, and drift is what this pins. Raise a limit on one side only and this fails.
 */
class TechRiderPatchLimitsTest extends TestCase
{
    private const string EDITOR_PATH = 'assets/js/components/BandSpace/TechRider/RiderPatchListEditor.vue';
    private const string GRID_PATH = 'assets/js/components/BandSpace/TechRider/RiderPatchGrid.vue';

    public function test_the_editor_row_cap_matches_the_constraint(): void
    {
        $this->assertSame(
            TechRiderPatchRows::MAX_ROWS_PER_DIRECTION,
            $this->parseInt(self::EDITOR_PATH, '/const MAX_ROWS_PER_DIRECTION = (\d+)/'),
            sprintf('The row cap has drifted. Update %s and %s together.', self::EDITOR_PATH, TechRiderPatchRows::class),
        );
    }

    public function test_the_grid_field_lengths_match_the_constraint(): void
    {
        $source = $this->read(self::GRID_PATH);

        preg_match('/const FIELD_LIMITS = \{([^}]+)}/', $source, $block);
        self::assertNotEmpty($block, sprintf('No FIELD_LIMITS declaration found in %s.', self::GRID_PATH));

        $found = [];
        preg_match_all('/(\w+):\s*(\d+)/', $block[1], $pairs, PREG_SET_ORDER);
        foreach ($pairs as $pair) {
            $found[$pair[1]] = (int) $pair[2];
        }

        $this->assertSame(
            [
                'name' => TechRiderPatchRows::MAX_NAME_LENGTH,
                'microphone' => TechRiderPatchRows::MAX_MICROPHONE_LENGTH,
                'routing' => TechRiderPatchRows::MAX_ROUTING_LENGTH,
            ],
            $found,
            sprintf('The field lengths have drifted. Update %s and %s together.', self::GRID_PATH, TechRiderPatchRows::class),
        );
    }

    private function parseInt(string $relativePath, string $pattern): int
    {
        preg_match($pattern, $this->read($relativePath), $matches);
        // Guards against the regex silently matching nothing, which would compare the constant
        // against zero and report a drift that is really a renamed variable.
        self::assertNotEmpty($matches, sprintf('Pattern %s matched nothing in %s.', $pattern, $relativePath));

        return (int) $matches[1];
    }

    private function read(string $relativePath): string
    {
        // tests/Unit/Validator/BandSpace/TechRider -> project root
        $path = \dirname(__DIR__, 5) . '/' . $relativePath;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}

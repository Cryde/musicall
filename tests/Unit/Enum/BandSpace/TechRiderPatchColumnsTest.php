<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\BandSpace;

use App\Enum\BandSpace\TechRiderPatchColumn;
use App\Enum\BandSpace\TechRiderPatchDirection;
use PHPUnit\Framework\TestCase;

/**
 * The patch list editor and the PDF must name the same columns the same way (#1089): they used to
 * disagree, so a band filled in « Micro » and the venue read « Type ». The labels are PHP enums for
 * the PDF and a JS constant for the editor, and this pins one against the other, the way
 * TechRiderPatchLimitsTest pins the row cap and the field lengths.
 */
class TechRiderPatchColumnsTest extends TestCase
{
    private const string CONSTANTS_PATH = 'assets/js/constants/techRiderPatchColumns.js';

    public function test_the_editor_columns_match_the_enum_in_order(): void
    {
        preg_match('/PATCH_LIST_COLUMNS = Object\.freeze\(\[(.+?)]\)/s', $this->source(), $block);
        self::assertNotEmpty($block, sprintf('No PATCH_LIST_COLUMNS declaration found in %s.', self::CONSTANTS_PATH));

        preg_match_all("/field: '(\\w+)', label: '([^']+)'/", $block[1], $pairs, PREG_SET_ORDER);
        $editor = array_map(static fn (array $pair): array => [$pair[1], $pair[2]], $pairs);

        $this->assertSame(
            array_map(static fn (TechRiderPatchColumn $column): array => [$column->value, $column->label()], TechRiderPatchColumn::cases()),
            $editor,
            sprintf('The patch list columns have drifted. Update %s and %s together.', self::CONSTANTS_PATH, TechRiderPatchColumn::class),
        );
    }

    public function test_the_editor_directions_match_the_enum(): void
    {
        preg_match("/inputs: '([^']+)',\\s*outputs: '([^']+)'/", $this->source(), $matches);
        self::assertNotEmpty($matches, sprintf('No PATCH_LIST_DIRECTIONS declaration found in %s.', self::CONSTANTS_PATH));

        $this->assertSame(
            [TechRiderPatchDirection::Input->label(), TechRiderPatchDirection::Output->label()],
            [$matches[1], $matches[2]],
        );
    }

    private function source(): string
    {
        // tests/Unit/Enum/BandSpace -> project root
        $path = \dirname(__DIR__, 4) . '/' . self::CONSTANTS_PATH;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}

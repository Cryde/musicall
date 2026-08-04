<?php declare(strict_types=1);

namespace App\Tests\Unit\Procedure\BandSpace;

use App\Procedure\BandSpace\TechRiderDuplicateProcedure;
use PHPUnit\Framework\TestCase;

/**
 * The duplicate dialog proposes a name in its field rather than submitting nothing and letting the
 * server apply its default, so it has to do the same arithmetic. If the two drift, duplicating a
 * rider whose name is near the limit pre-fills something the server then refuses, and the one click
 * path breaks for exactly the people with the most descriptive names.
 *
 * Same approach as the colour palette and the stage plot limits: the duplication is deliberate, and
 * this is the contract between the two copies.
 */
class TechRiderDuplicateNameLimitsTest extends TestCase
{
    private const string CONSTANTS_PATH = 'assets/js/constants/techRider.js';

    public function test_the_client_name_limit_matches_the_procedure(): void
    {
        preg_match('/export const MAX_NAME_LENGTH = (\d+)/', $this->read(), $matches);
        self::assertNotEmpty($matches, sprintf('No MAX_NAME_LENGTH export found in %s.', self::CONSTANTS_PATH));

        $this->assertSame(TechRiderDuplicateProcedure::MAX_NAME_LENGTH, (int) $matches[1]);
    }

    public function test_the_client_copy_suffix_matches_the_procedure(): void
    {
        preg_match("/export const COPY_SUFFIX = '([^']+)'/", $this->read(), $matches);
        self::assertNotEmpty($matches, sprintf('No COPY_SUFFIX export found in %s.', self::CONSTANTS_PATH));

        $this->assertSame(TechRiderDuplicateProcedure::NAME_SUFFIX, $matches[1]);
    }

    private function read(): string
    {
        // tests/Unit/Procedure/BandSpace -> project root
        $path = \dirname(__DIR__, 4) . '/' . self::CONSTANTS_PATH;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}

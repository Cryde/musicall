<?php declare(strict_types=1);

namespace App\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;

/**
 * Invitation activities carry the invitee's address, and every builder that renders a BandSpaceActivity
 * must mask it. Three do so today, but only one of them serves a module that can carry an address, so a
 * fourth builder, or a provider widened to feed an existing one across modules, would reopen the leak
 * with every behaviour test still green.
 *
 * This asserts the wiring instead: builder number four is the point. Same shape as
 * BandSpaceWriteGuardCoverageTest, for the same reason.
 */
class BandSpaceActivityPayloadMaskCoverageTest extends TestCase
{
    private const string BUILDER_DIR = 'src/Service/Builder';

    private const string MASK_MARKER = 'ActivityPayloadMask::mask(';

    public function test_every_builder_rendering_an_activity_payload_goes_through_the_mask(): void
    {
        $unmasked = [];
        foreach ($this->activityPayloadBuilders() as $relativePath => $source) {
            if (!str_contains($source, self::MASK_MARKER)) {
                $unmasked[] = $relativePath;
            }
        }

        self::assertSame(
            [],
            $unmasked,
            "These builders put a stored activity payload on the wire without masking it.\n"
            . "Assign App\\Privacy\\ActivityPayloadMask::mask(\$entity->payload) instead of the raw payload.\n",
        );
    }

    /**
     * Guards against the scan silently matching nothing, which would make the test above pass vacuously.
     */
    public function test_the_scan_finds_the_known_activity_builders(): void
    {
        self::assertSame(
            [
                'src/Service/Builder/BandSpace/BandSpaceActivityBuilder.php',
                'src/Service/Builder/BandSpace/File/BandSpaceFileActivityBuilder.php',
                'src/Service/Builder/BandSpace/TaskActivityBuilder.php',
            ],
            array_keys($this->activityPayloadBuilders()),
        );
    }

    /**
     * Every builder that both knows the BandSpaceActivity entity and writes a payload onto a DTO.
     *
     * @return array<string, string> relative path => file contents
     */
    private function activityPayloadBuilders(): array
    {
        // tests/Unit/Privacy -> project root
        $directory = \dirname(__DIR__, 3) . '/' . self::BUILDER_DIR;
        self::assertDirectoryExists($directory);

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);

            if (!str_contains($source, 'BandSpaceActivity') || !str_contains($source, '->payload = ')) {
                continue;
            }

            $files[self::BUILDER_DIR . '/' . $iterator->getSubPathname()] = $source;
        }

        ksort($files);

        return $files;
    }
}

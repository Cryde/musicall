<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\File;

use App\Service\BandSpace\File\GuitarProSignature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GuitarProSignatureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingFiles(): iterable
    {
        yield 'gp3' => ['tab.gp3', "\x18FICHIER GUITAR PRO v3.00"];
        yield 'gp4' => ['tab.gp4', "\x18FICHIER GUITAR PRO v4.06"];
        yield 'gp5 5.00' => ['tab.gp5', "\x18FICHIER GUITAR PRO v5.00"];
        yield 'gp5 5.10' => ['tab.gp5', "\x18FICHIER GUITAR PRO v5.10"];
        yield 'gpx compressed' => ['tab.gpx', "BCFZ\x04\x10\x01\x00"];
        yield 'gpx uncompressed' => ['tab.gpx', "BCFS\x00\x00\x00\x00"];
        yield 'upper case extension' => ['Solo FINAL.GP5', "\x18FICHIER GUITAR PRO v5.00"];
    }

    #[DataProvider('matchingFiles')]
    public function test_a_file_with_its_format_signature_matches(string $fileName, string $head): void
    {
        $this->assertTrue(GuitarProSignature::matches($fileName, $head));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedFiles(): iterable
    {
        yield 'gp5 holding a gp4' => ['tab.gp5', "\x18FICHIER GUITAR PRO v4.06"];
        yield 'gp5 holding a gpx' => ['tab.gp5', "BCFZ\x04\x10\x01\x00"];
        yield 'gpx holding a gp5' => ['tab.gpx', "\x18FICHIER GUITAR PRO v5.00"];
        yield 'signature without its length byte' => ['tab.gp5', 'FICHIER GUITAR PRO v5.00'];
        yield 'empty gp5' => ['tab.gp5', ''];
        yield 'gp5 signature behind another extension' => ['tab.bin', "\x18FICHIER GUITAR PRO v5.00"];
        yield 'gp5 signature without an extension' => ['tab', "\x18FICHIER GUITAR PRO v5.00"];
        yield 'gp5 signature behind a double extension' => ['tab.gp5.exe', "\x18FICHIER GUITAR PRO v5.00"];
    }

    #[DataProvider('refusedFiles')]
    public function test_a_file_without_its_format_signature_does_not_match(string $fileName, string $head): void
    {
        $this->assertFalse(GuitarProSignature::matches($fileName, $head));
    }
}

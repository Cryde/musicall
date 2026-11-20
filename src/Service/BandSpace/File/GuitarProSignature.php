<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

/**
 * Guitar Pro 3 to 6 files have no MIME type of their own (finfo says application/octet-stream),
 * so they are recognised by their extension and the signature their format starts with.
 */
final class GuitarProSignature
{
    private const int HEAD_LENGTH = 32;

    /**
     * GP3 to GP5 start with a length byte then their version string, GP6 with a BCF container tag.
     */
    private const array SIGNATURE_PATTERN_BY_EXTENSION = [
        'gp3' => '/^.FICHIER GUITAR PRO v3/s',
        'gp4' => '/^.FICHIER GUITAR PRO v4/s',
        'gp5' => '/^.FICHIER GUITAR PRO v5/s',
        'gpx' => '/^BCF[ZS]/',
    ];

    public static function matchesFile(string $fileName, string $path): bool
    {
        if (self::patternFor($fileName) === null) {
            return false;
        }
        $head = file_get_contents($path, false, null, 0, self::HEAD_LENGTH);

        return $head !== false && self::matches($fileName, $head);
    }

    public static function matches(string $fileName, string $head): bool
    {
        $pattern = self::patternFor($fileName);

        return $pattern !== null && preg_match($pattern, $head) === 1;
    }

    private static function patternFor(string $fileName): ?string
    {
        return self::SIGNATURE_PATTERN_BY_EXTENSION[strtolower(pathinfo($fileName, PATHINFO_EXTENSION))] ?? null;
    }
}

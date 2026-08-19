<?php declare(strict_types=1);

namespace App\Exception\BandSpace;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The only thing a member ever learns about a refused upload, an attachment or a restore, because
 * every one of those endpoints answers with this message and nothing else. It is written to be read
 * by a person: raw byte counts are unreadable at gigabyte scale, and the shortfall is stated outright
 * so a member who cannot free space knows how much to ask an admin for rather than guessing.
 */
class QuotaExceededException extends UnprocessableEntityHttpException
{
    public function __construct(int $quotaBytes, int $usedBytes, int $incomingBytes)
    {
        parent::__construct(sprintf(
            'Quota de stockage dépassé : %s utilisés sur %s autorisés, il manque %s pour ajouter %s.',
            self::humanizeBytes($usedBytes),
            self::humanizeBytes($quotaBytes),
            self::humanizeBytes($usedBytes + $incomingBytes - $quotaBytes),
            self::humanizeBytes($incomingBytes),
        ));
    }

    /**
     * French units, matching assets/js/utils/formatBytes.js unit for unit, separator for separator and
     * decimal for decimal. This sentence is read next to the quota bar, which formats its own figures
     * with that helper, and two spellings of the same number in one view read as two numbers.
     *
     * number_format, not sprintf('%.1f'): sprintf rounds an exact decimal tie half to even while the
     * JavaScript toFixed rounds it half up, so 1280 bytes printed as 1.2 Ko here and 1.3 Ko in the bar
     * beside it. Ties are reachable because a used total is a sum of arbitrary file sizes, so any
     * multiple of 256 bytes lands on one. The separators are passed explicitly because the default
     * thousands separator would group a four-digit Go figure and toFixed never groups.
     */
    private static function humanizeBytes(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes . ' o',
            $bytes < 1024 ** 2 => number_format($bytes / 1024, 1, '.', '') . ' Ko',
            $bytes < 1024 ** 3 => number_format($bytes / 1024 ** 2, 1, '.', '') . ' Mo',
            default => number_format($bytes / 1024 ** 3, 2, '.', '') . ' Go',
        };
    }
}

<?php declare(strict_types=1);

namespace App\Exception\BandSpace;

use App\Exception\TranslatableExceptionTrait;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The only thing a member ever learns about a refused upload, an attachment or a restore, because
 * every one of those endpoints answers with this message and nothing else. It is written to be read
 * by a person: raw byte counts are unreadable at gigabyte scale, and the shortfall is stated outright
 * so a member who cannot free space knows how much to ask an admin for rather than guessing.
 */
class QuotaExceededException extends UnprocessableEntityHttpException implements TranslatableInterface
{
    use TranslatableExceptionTrait;

    /**
     * The units of assets/js/utils/formatBytes.js, per app locale. A locale without an entry gets French.
     */
    private const array BYTE_UNITS = [
        'fr' => ['o', 'Ko', 'Mo', 'Go'],
        'en' => ['B', 'KB', 'MB', 'GB'],
    ];

    private const string DEFAULT_LOCALE = 'fr';

    /**
     * The figures by placeholder, in bytes: they are written in units once trans() knows the language.
     *
     * @var array<string, int>
     */
    private readonly array $parameters;

    public function __construct(int $quotaBytes, int $usedBytes, int $incomingBytes)
    {
        parent::__construct('band_space.storage.quota_exceeded');

        $this->parameters = [
            'used' => $usedBytes,
            'quota' => $quotaBytes,
            'missing' => $usedBytes + $incomingBytes - $quotaBytes,
            'incoming' => $incomingBytes,
        ];
    }

    /**
     * The figures in French units, for a caller that does not say which language it writes in.
     *
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parametersIn(self::DEFAULT_LOCALE);
    }

    /**
     * The units follow the language of the sentence: the locale asked for, else the translator's own.
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(
            $this->getMessage(),
            $this->parametersIn($locale ?? $translator->getLocale()),
            null,
            $locale,
        );
    }

    /**
     * @return array<string, string>
     */
    private function parametersIn(string $locale): array
    {
        $units = self::BYTE_UNITS[$locale] ?? self::BYTE_UNITS[self::DEFAULT_LOCALE];

        return array_map(static fn (int $bytes): string => self::humanizeBytes($bytes, $units), $this->parameters);
    }

    /**
     * Matches assets/js/utils/formatBytes.js unit for unit, separator for separator and decimal for
     * decimal, in French and in English. This sentence is read next to the quota bar, which formats
     * its own figures with that helper, and two spellings of the same number in one view read as two
     * numbers. The figure keeps a dot in both languages, as the helper does.
     *
     * number_format, not sprintf('%.1f'): sprintf rounds an exact decimal tie half to even while the
     * JavaScript toFixed rounds it half up, so 1280 bytes printed as 1.2 Ko here and 1.3 Ko in the bar
     * beside it. Ties are reachable because a used total is a sum of arbitrary file sizes, so any
     * multiple of 256 bytes lands on one. The separators are passed explicitly because the default
     * thousands separator would group a four-digit Go figure and toFixed never groups.
     *
     * @param array{string, string, string, string} $units bytes, kilobytes, megabytes, gigabytes
     */
    private static function humanizeBytes(int $bytes, array $units): string
    {
        [$byte, $kilobyte, $megabyte, $gigabyte] = $units;

        return match (true) {
            $bytes < 1024 => $bytes . ' ' . $byte,
            $bytes < 1024 ** 2 => number_format($bytes / 1024, 1, '.', '') . ' ' . $kilobyte,
            $bytes < 1024 ** 3 => number_format($bytes / 1024 ** 2, 1, '.', '') . ' ' . $megabyte,
            default => number_format($bytes / 1024 ** 3, 2, '.', '') . ' ' . $gigabyte,
        };
    }
}

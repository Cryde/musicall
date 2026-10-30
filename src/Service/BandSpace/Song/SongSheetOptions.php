<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song;

use ApiPlatform\Metadata\Parameters;
use ApiPlatform\State\ParameterNotFound;

final readonly class SongSheetOptions
{
    /** What the `chords` and `singers` query parameters accept. */
    public const array FLAG_VALUES = ['0', '1', 'true', 'false'];

    public function __construct(
        public bool $showChords = true,
        public bool $showSingers = true,
        /** Semitones, applied to the chords and the key of the print only. */
        public int $transpose = 0,
    ) {
    }

    /** The `chords`, `singers` and `transpose` query parameters of a PDF export, already validated. */
    public static function fromParameters(?Parameters $parameters): self
    {
        return new self(
            showChords: self::flag($parameters?->get('chords')?->getValue()),
            showSingers: self::flag($parameters?->get('singers')?->getValue()),
            transpose: (int) self::valueOf($parameters?->get('transpose')?->getValue(), '0'),
        );
    }

    /** Both default to on: a printed song sheet is read with its chords and its singers. */
    private static function flag(mixed $value): bool
    {
        return in_array(self::valueOf($value, '1'), ['1', 'true'], true);
    }

    private static function valueOf(mixed $value, string $default): string
    {
        return $value instanceof ParameterNotFound || $value === null || $value === '' ? $default : (string) $value;
    }
}

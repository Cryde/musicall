<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song\ChordPro;

/**
 * Moves chords by semitones, in English (C, F#m7, Bb/D) or French (Do, Fa#m7, Sib/Ré) notation, each
 * chord keeping the notation it was written in. The twin of the transpose half of chordpro.js.
 */
final readonly class ChordTransposer
{
    private const array ENGLISH_SHARPS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
    private const array ENGLISH_FLATS = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];
    private const array LATIN_SHARPS = ['Do', 'Do#', 'Ré', 'Ré#', 'Mi', 'Fa', 'Fa#', 'Sol', 'Sol#', 'La', 'La#', 'Si'];
    private const array LATIN_FLATS = ['Do', 'Réb', 'Ré', 'Mib', 'Mi', 'Fa', 'Solb', 'Sol', 'Lab', 'La', 'Sib', 'Si'];
    private const array NATURALS = [
        'C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11,
        'Do' => 0, 'Ré' => 2, 'Re' => 2, 'Mi' => 4, 'Fa' => 5, 'Sol' => 7, 'La' => 9, 'Si' => 11,
    ];
    private const string NOTE = '(Do|Ré|Re|Mi|Fa|Sol|La|Si|[A-G])(#|b|♯|♭)?';
    /** Major keys, by pitch class, written with flats; then the minor ones. */
    private const array FLAT_MAJOR_KEYS = [5, 10, 3, 8, 1, 6];
    private const array FLAT_MINOR_KEYS = [2, 7, 0, 5, 10, 3];
    private const string MINOR_SUFFIX = '/^\s*(m(?!aj)|min|mineur|minor|-)/iu';

    /** A chord moved by $steps semitones, or unchanged when it is not one this can read (`N.C.`). */
    public function transposeChord(string $name, int $steps, bool $flats): string
    {
        if ($steps % 12 === 0 || preg_match('/^' . self::NOTE . '([^\/]*)(?:\/' . self::NOTE . ')?$/u', $name, $match) !== 1) {
            return $name;
        }

        $out = $this->spell($this->pitchOf($match[1], $match[2]) + $steps, $this->isLatin($match[1]), $flats) . $match[3];
        if (($match[4] ?? '') !== '') {
            $out .= '/' . $this->spell($this->pitchOf($match[4], $match[5] ?? '') + $steps, $this->isLatin($match[4]), $flats);
        }

        return $out;
    }

    /** The song's key moved by $steps, keeping whatever follows the note (« m », « mineur »), or null. */
    public function transposeKey(?string $key, int $steps): ?string
    {
        if (preg_match('/^' . self::NOTE . '(.*)$/u', trim($key ?? ''), $match) !== 1) {
            return null;
        }

        $pitch = $this->pitchOf($match[1], $match[2]) + $steps;
        $latin = $this->isLatin($match[1]);
        $flats = $this->prefersFlats($this->spell($pitch, $latin, false) . $match[3], $steps);

        return $this->spell($pitch, $latin, $flats) . $match[3];
    }

    /** Every chord in the source moved by $steps. Only chords are rewritten, the rest stays byte for byte. */
    public function transposeSource(?string $source, int $steps, ?string $key): string
    {
        $flats = $this->prefersFlats($this->transposeKey($key, $steps), $steps);

        $lines = array_map(
            fn (string $line): string => preg_match(ChordProParser::DIRECTIVE, trim($line)) === 1
                ? $line
                : (string) preg_replace_callback(
                    '/\[([^\]]+)\]/u',
                    fn (array $chord): string => '[' . $this->transposeChord(trim($chord[1]), $steps, $flats) . ']',
                    $line,
                ),
            explode("\n", $source ?? ''),
        );

        return implode("\n", $lines);
    }

    /** Whether the key the song lands in is written with flats; with no known key, the direction decides. */
    public function prefersFlats(?string $targetKey, int $steps): bool
    {
        if (preg_match('/^' . self::NOTE . '(.*)$/u', trim($targetKey ?? ''), $match) !== 1) {
            return $steps < 0;
        }
        $pitch = $this->pitchOf($match[1], $match[2]);

        return preg_match(self::MINOR_SUFFIX, $match[3]) === 1
            ? in_array($pitch, self::FLAT_MINOR_KEYS, true)
            : in_array($pitch, self::FLAT_MAJOR_KEYS, true);
    }

    private function pitchOf(string $natural, string $accidental): int
    {
        $shift = match ($accidental) {
            '#', '♯' => 1,
            'b', '♭' => -1,
            default => 0,
        };

        return (self::NATURALS[$natural] + $shift + 12) % 12;
    }

    private function spell(int $pitch, bool $latin, bool $flats): string
    {
        $names = match (true) {
            $latin && $flats => self::LATIN_FLATS,
            $latin => self::LATIN_SHARPS,
            $flats => self::ENGLISH_FLATS,
            default => self::ENGLISH_SHARPS,
        };

        return $names[(($pitch % 12) + 12) % 12];
    }

    private function isLatin(string $natural): bool
    {
        return mb_strlen($natural) > 1;
    }
}

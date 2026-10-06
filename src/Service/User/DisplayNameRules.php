<?php declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;

/**
 * What a chosen name (profile name or stage name) may contain. An allow list of characters would
 * refuse « Mötley Crüe », « AC/DC » or a name in another script, so this refuses what harms a reader
 * instead: invisible or direction changing characters, emoji, stacked accents, a name that looks
 * like a handle or a system label.
 */
final class DisplayNameRules
{
    public const string FORBIDDEN_CHARACTER = 'forbidden_character';
    public const string EMOJI = 'emoji';
    public const string STACKED_MARKS = 'stacked_marks';
    public const string NO_LETTER_OR_DIGIT = 'no_letter_or_digit';
    public const string LEADING_AT = 'leading_at';
    public const string RESERVED = 'reserved';

    /**
     * Control, private use and unassigned characters, line breaks, and the invisible ones that hide
     * or reorder text: direction marks and overrides, zero width space, word joiner, BOM, invisible
     * operators, soft hyphen, and the Hangul fillers that render as a blank letter. Not the whole of
     * \p{Cf}: the zero width joiner and non joiner are needed by Persian and Indic scripts.
     */
    private const string FORBIDDEN_PATTERN = '/[\p{Cc}\p{Co}\p{Cn}\p{Zl}\p{Zp}\x{00AD}\x{061C}\x{180E}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}\x{115F}\x{1160}\x{3164}\x{FFA0}]/u';

    /**
     * Pictographs, which also covers the symbols a device may draw as emoji (♪, ★, ™), plus what
     * builds the rest: flag letters, the emoji presentation selector, the keycap mark, skin tones and
     * tag characters.
     */
    private const string EMOJI_PATTERN = '/[\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}\x{FE0F}\x{20E3}\x{1F3FB}-\x{1F3FF}\x{E0020}-\x{E007F}]/u';

    /** One or two marks cover every real accent; more is text spilling over its neighbours. */
    private const string STACKED_MARKS_PATTERN = '/\p{M}{3,}/u';

    private const array RESERVED_NAMES = [User::DELETED_DISPLAY_NAME, 'MusicAll'];

    /** NFC, every run of whitespace as one space, trimmed; blank means no name at all. */
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);
        if ($normalized === false) {
            // Not valid UTF-8: left as is, and violation() refuses it since /u patterns fail on it.
            return $name;
        }
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

        return $normalized === '' ? null : $normalized;
    }

    /** The first rule a normalized name breaks, or null when it is acceptable. */
    public static function violation(string $name): ?string
    {
        return match (true) {
            preg_match(self::FORBIDDEN_PATTERN, $name) !== 0 => self::FORBIDDEN_CHARACTER,
            preg_match(self::EMOJI_PATTERN, $name) === 1 => self::EMOJI,
            preg_match(self::STACKED_MARKS_PATTERN, $name) === 1 => self::STACKED_MARKS,
            preg_match('/[\p{L}\p{N}]/u', $name) !== 1 => self::NO_LETTER_OR_DIGIT,
            str_starts_with($name, '@') => self::LEADING_AT,
            self::isReserved($name) => self::RESERVED,
            default => null,
        };
    }

    private static function isReserved(string $name): bool
    {
        return array_any(self::RESERVED_NAMES, static fn (string $reserved): bool => mb_strtolower($reserved) === mb_strtolower($name));
    }
}

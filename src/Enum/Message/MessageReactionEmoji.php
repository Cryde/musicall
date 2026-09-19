<?php declare(strict_types=1);

namespace App\Enum\Message;

/**
 * The reactions a chat message accepts (#968).
 *
 * A closed list rather than free input. Free input would mean arbitrary user text stored and
 * rendered on every message, and it would defeat the point of a reaction, which is that five people
 * pick the *same* one and it collapses into a single count.
 *
 * The stored value is the slug, never the character. That keeps the column plain short ascii, keeps
 * a multi-byte emoji out of the URL the remove endpoint takes it in, and makes re-skinning a
 * reaction a code change rather than an UPDATE over every row.
 *
 * Declaration order is display order, and it is the order the aggregate comes back in.
 *
 * Kept in step with assets/js/constants/chatReactions.js by
 * tests/Unit/Enum/Message/ChatReactionPaletteTest.php, which fails if either side drifts.
 */
enum MessageReactionEmoji: string
{
    case ThumbsUp = 'thumbs_up';
    case ThumbsDown = 'thumbs_down';
    case Heart = 'heart';
    case Laugh = 'laugh';
    case Party = 'party';
    case Fire = 'fire';
    case Guitar = 'guitar';
    case Check = 'check';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** Presentation only: the API hands it out, nothing stores it or matches on it. */
    public function character(): string
    {
        return match ($this) {
            self::ThumbsUp => '👍',
            self::ThumbsDown => '👎',
            self::Heart => '❤️',
            self::Laugh => '😂',
            self::Party => '🎉',
            self::Fire => '🔥',
            self::Guitar => '🎸',
            self::Check => '✅',
        };
    }
}

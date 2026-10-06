<?php declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\User;

/** What a report keeps of its target at the time it is made, so a later edit or delete loses nothing. */
final readonly class ReportTarget
{
    /** Long enough for any real message, short enough that a pasted novel does not bloat the table. */
    private const int TEXT_MAX_LENGTH = 5000;

    /**
     * @param array<string, scalar|null> $context what the moderator needs to place it (topic, band, page)
     */
    public function __construct(
        public ?User $author,
        public string $text,
        public array $context = [],
    ) {
    }

    /** Plain text, markup removed, whitespace collapsed and capped. */
    public static function text(string ...$parts): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags(implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')))));

        return mb_substr($text, 0, self::TEXT_MAX_LENGTH);
    }
}

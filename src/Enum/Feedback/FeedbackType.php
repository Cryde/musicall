<?php declare(strict_types=1);

namespace App\Enum\Feedback;

/**
 * What a piece of feedback is, which decides how it is triaged rather than where it came from.
 *
 * Kept deliberately short. A longer list pushes the cost of classifying onto the person reporting,
 * who is already doing us a favour, and every extra case is one more way for the same report to
 * land in two different buckets.
 */
enum FeedbackType: string
{
    case Bug = 'bug';
    case Suggestion = 'suggestion';
    case Question = 'question';
    case Other = 'other';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Bug',
            self::Suggestion => 'Suggestion',
            self::Question => 'Question',
            self::Other => 'Autre',
        };
    }
}

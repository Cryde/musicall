<?php declare(strict_types=1);

namespace App\Enum\Feedback;

/**
 * Where a piece of feedback sits in the triage queue.
 *
 * `New` is the count the admin badge shows, so it means "nobody has looked at this yet" rather than
 * "recently created": reading a report and deciding it needs nothing still moves it to `Done`.
 */
enum FeedbackStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::InProgress => 'En cours',
            self::Done => 'Traité',
        };
    }
}

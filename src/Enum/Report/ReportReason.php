<?php declare(strict_types=1);

namespace App\Enum\Report;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Fake = 'fake';
    case Other = 'other';

    /** @return list<string> for Assert\Choice */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

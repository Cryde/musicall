<?php declare(strict_types=1);

namespace App\Enum\User;

/** The mobile platform a push token was issued for. Builds released before iOS existed send `android` from an iPhone too. */
enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

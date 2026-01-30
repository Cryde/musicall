<?php

declare(strict_types=1);

namespace App\Enum\Teacher;

enum SessionDuration: string
{
    case THIRTY_MINUTES = '30min';
    case ONE_HOUR = '1h';
    case ONE_HOUR_THIRTY = '1h30';
    case TWO_HOURS = '2h';

    public function getLabel(): string
    {
        return match ($this) {
            self::THIRTY_MINUTES => '30 minutes',
            self::ONE_HOUR => '1 heure',
            self::ONE_HOUR_THIRTY => '1h30',
            self::TWO_HOURS => '2 heures',
        };
    }

    public function getMinutes(): int
    {
        return match ($this) {
            self::THIRTY_MINUTES => 30,
            self::ONE_HOUR => 60,
            self::ONE_HOUR_THIRTY => 90,
            self::TWO_HOURS => 120,
        };
    }
}

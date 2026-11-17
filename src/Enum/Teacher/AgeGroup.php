<?php

declare(strict_types=1);

namespace App\Enum\Teacher;

enum AgeGroup: string
{
    case CHILDREN = 'children';
    case TEENAGERS = 'teenagers';
    case ADULTS = 'adults';
    case SENIORS = 'seniors';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CHILDREN => 'Enfants',
            self::TEENAGERS => 'Adolescents',
            self::ADULTS => 'Adultes',
            self::SENIORS => 'Seniors',
        };
    }
}

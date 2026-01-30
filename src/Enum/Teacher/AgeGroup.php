<?php

declare(strict_types=1);

namespace App\Enum\Teacher;

enum AgeGroup: string
{
    case CHILDREN = 'children';
    case TEENAGERS = 'teenagers';
    case ADULTS = 'adults';
    case SENIORS = 'seniors';

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

<?php

declare(strict_types=1);

namespace App\Enum\Musician;

enum SkillLevel: string
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
    case PROFESSIONAL = 'professional';

    public function getLabel(): string
    {
        return match ($this) {
            self::BEGINNER => 'Débutant',
            self::INTERMEDIATE => 'Intermédiaire',
            self::ADVANCED => 'Avancé',
            self::PROFESSIONAL => 'Professionnel',
        };
    }

    public function getSortOrder(): int
    {
        return match ($this) {
            self::BEGINNER => 1,
            self::INTERMEDIATE => 2,
            self::ADVANCED => 3,
            self::PROFESSIONAL => 4,
        };
    }
}

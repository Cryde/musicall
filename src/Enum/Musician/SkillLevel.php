<?php

declare(strict_types=1);

namespace App\Enum\Musician;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SkillLevel: string implements TranslatableInterface
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
    case PROFESSIONAL = 'professional';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(match ($this) {
            self::BEGINNER => 'skill_level.beginner',
            self::INTERMEDIATE => 'skill_level.intermediate',
            self::ADVANCED => 'skill_level.advanced',
            self::PROFESSIONAL => 'skill_level.professional',
        }, [], 'reference', $locale);
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

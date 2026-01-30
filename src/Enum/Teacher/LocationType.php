<?php

declare(strict_types=1);

namespace App\Enum\Teacher;

enum LocationType: string
{
    case TEACHER_PLACE = 'teacher_place';
    case STUDENT_PLACE = 'student_place';
    case ONLINE = 'online';

    public function getLabel(): string
    {
        return match ($this) {
            self::TEACHER_PLACE => 'Chez le professeur',
            self::STUDENT_PLACE => 'Chez l\'élève',
            self::ONLINE => 'En ligne',
        };
    }
}

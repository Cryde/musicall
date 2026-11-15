<?php

declare(strict_types=1);

namespace App\Enum\Teacher;

use App\Enum\Service\ServicePlaceType;

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

    /** Where a lesson happens, as the service model stores it (#1318); the teacher API keeps its own names. */
    public function placeType(): ServicePlaceType
    {
        return match ($this) {
            self::TEACHER_PLACE => ServicePlaceType::AtMyPlace,
            self::STUDENT_PLACE => ServicePlaceType::AtClientPlace,
            self::ONLINE => ServicePlaceType::Remote,
        };
    }

    public static function fromPlaceType(ServicePlaceType $placeType): self
    {
        return array_find(self::cases(), static fn (self $type): bool => $type->placeType() === $placeType)
            ?? throw new \ValueError(sprintf('No lesson place is %s', $placeType->value));
    }
}

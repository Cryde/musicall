<?php

declare(strict_types=1);

namespace App\Enum\Service;

use App\Entity\Service\Service;
use App\Entity\Service\SoundEngineeringDetails;
use App\Entity\Service\TeachingDetails;

/** One part of a service page, counted by its completeness and by what it needs before it can be published (#1406). */
enum ServicePart: string
{
    case Headline = 'headline';
    case Description = 'description';
    case Instruments = 'instruments';
    case StudentLevels = 'student_levels';
    case AgeGroups = 'age_groups';
    case LevelGoals = 'level_goals';
    case Songs = 'songs';
    case Places = 'places';
    case Availability = 'availability';
    case Offers = 'offers';
    case Specialities = 'specialities';
    case BeforeAfter = 'before_after';
    case Gear = 'gear';
    case Styles = 'styles';
    case Turnaround = 'turnaround';
    case Formats = 'formats';

    public function isFilledIn(Service $service): bool
    {
        return match ($this) {
            self::Headline => trim((string) $service->headline) !== '',
            self::Description => trim((string) $service->description) !== '',
            self::Instruments => !$service->instruments->isEmpty(),
            self::StudentLevels => TeachingDetails::of($service)->studentLevels !== [],
            self::AgeGroups => TeachingDetails::of($service)->ageGroups !== [],
            self::LevelGoals => TeachingDetails::of($service)->levelGoalList() !== [],
            self::Songs => TeachingDetails::of($service)->songs !== [],
            self::Places => !$service->places->isEmpty(),
            self::Availability => !$service->availability->isEmpty(),
            self::Offers => !$service->offers->isEmpty(),
            self::Specialities => $service->specialities !== [],
            // Both halves: one alone has nothing to compare with.
            self::BeforeAfter => $service->audio->count() === count(BeforeAfterSide::cases()),
            self::Gear => SoundEngineeringDetails::of($service)->gear !== [],
            self::Styles => !$service->styles->isEmpty(),
            self::Turnaround => SoundEngineeringDetails::of($service)->turnaroundDays !== null,
            self::Formats => SoundEngineeringDetails::of($service)->formats !== [],
        };
    }
}

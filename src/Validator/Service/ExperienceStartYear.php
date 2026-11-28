<?php

declare(strict_types=1);

namespace App\Validator\Service;

use Symfony\Component\Validator\Constraint;

/** The year a member started (#1453): never in the future, at most YearsOfExperience::MAX years back. */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ExperienceStartYear extends Constraint
{
    public string $message = 'service.experience_start_year_invalid';
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

use App\ApiResource\Teacher\Private\Input\Availability;
use App\ApiResource\Teacher\Private\Input\Location;
use App\ApiResource\Teacher\Private\Input\Package;
use App\ApiResource\Teacher\Private\Input\Pricing;
use App\ApiResource\Teacher\Private\Input\SocialLink;
use Symfony\Component\Validator\Constraints as Assert;

class TeacherProfileInput
{
    #[Assert\NotBlank(message: 'profile.teacher.description_required', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public ?string $description = null;

    #[Assert\NotNull(message: 'profile.teacher.years_of_experience_required', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    #[Assert\PositiveOrZero(message: 'profile.teacher.years_of_experience_positive', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    #[Assert\LessThanOrEqual(value: 70, message: 'profile.teacher.years_of_experience_too_high', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public ?int $yearsOfExperience = null;

    /** @var string[] */
    #[Assert\All([
        new Assert\Choice(
            choices: ['beginner', 'intermediate', 'advanced'],
            message: 'profile.teacher.level_invalid',
        ),
    ])]
    public array $studentLevels = [];

    /** @var string[] */
    #[Assert\All([
        new Assert\Choice(
            choices: ['children', 'teenagers', 'adults', 'seniors'],
            message: 'profile.teacher.age_group_invalid',
        ),
    ])]
    public array $ageGroups = [];

    public ?string $courseTitle = null;

    public bool $offersTrial = false;

    #[Assert\PositiveOrZero(message: 'profile.teacher.trial_lesson_price_positive')]
    public ?int $trialPrice = null;

    /** @var Location[] */
    #[Assert\Valid]
    public $locations = [];

    /** @var string[] */
    #[Assert\Count(min: 1, minMessage: 'profile.teacher.instruments_required', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public array $instrumentIds = [];

    /** @var string[] */
    public array $styleIds = [];

    /** @var Pricing[] */
    #[Assert\Valid]
    public $pricing = [];

    /** @var Availability[] */
    #[Assert\Valid]
    public $availability = [];

    /** @var Package[] */
    #[Assert\Valid]
    public $packages = [];

    /** @var SocialLink[] */
    #[Assert\Valid]
    public $socialLinks = [];
}

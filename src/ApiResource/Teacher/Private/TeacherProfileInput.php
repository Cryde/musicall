<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

use App\ApiResource\Teacher\Private\Input\Availability;
use App\ApiResource\Teacher\Private\Input\LevelGoal;
use App\ApiResource\Teacher\Private\Input\Location;
use App\ApiResource\Teacher\Private\Input\Package;
use App\ApiResource\Teacher\Private\Input\Pricing;
use App\ApiResource\Teacher\Private\Input\SocialLink;
use App\ApiResource\Teacher\Private\Input\Song;
use App\Service\Service\YearsOfExperience;
use App\Validator\Service\ExperienceStartYear;
use App\Validator\Teacher\ValidLevelGoals;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every field is null when left out of the request. On an edit, null keeps what is stored (#1327): an app version
 * that does not know a field yet must not reset it. On a create, null is the empty value.
 */
#[ValidLevelGoals]
class TeacherProfileInput
{
    #[Assert\NotBlank(message: 'profile.teacher.description_required', groups: ['teacher_profile_create'])]
    #[Assert\NotBlank(message: 'profile.teacher.description_required', allowNull: true, groups: ['teacher_profile_edit'])]
    public ?string $description = null;

    /** What the app sends, stored as the start year it stands for (#1453). A create needs it or the start year. */
    #[Assert\When(
        expression: 'this.experienceStartYear === null',
        constraints: [new Assert\NotNull(message: 'profile.teacher.years_of_experience_required')],
        groups: ['teacher_profile_create'],
    )]
    #[Assert\PositiveOrZero(message: 'profile.teacher.years_of_experience_positive', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    #[Assert\LessThanOrEqual(value: YearsOfExperience::MAX, message: 'profile.teacher.years_of_experience_too_high', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public ?int $yearsOfExperience = null;

    /** What the web sends (#1453): wins over `yearsOfExperience` when both are. */
    #[ExperienceStartYear]
    public ?int $experienceStartYear = null;

    /** @var string[]|null */
    #[Assert\All([
        new Assert\Choice(
            choices: ['beginner', 'intermediate', 'advanced'],
            message: 'profile.teacher.level_invalid',
        ),
    ])]
    public ?array $studentLevels = null;

    /** @var string[]|null */
    #[Assert\All([
        new Assert\Choice(
            choices: ['children', 'teenagers', 'adults', 'seniors'],
            message: 'profile.teacher.age_group_invalid',
        ),
    ])]
    public ?array $ageGroups = null;

    public ?string $courseTitle = null;

    public ?bool $offersTrial = null;

    /** Null means « no price » when sent with `offersTrial`, and « keep it » without: both clients send null for a free trial. */
    #[Assert\PositiveOrZero(message: 'profile.teacher.trial_lesson_price_positive')]
    public ?int $trialPrice = null;

    /** @var Location[]|null */
    #[Assert\Valid]
    public ?array $locations = null;

    /** @var string[]|null */
    #[Assert\NotNull(message: 'profile.teacher.instruments_required', groups: ['teacher_profile_create'])]
    #[Assert\Count(min: 1, minMessage: 'profile.teacher.instruments_required', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public ?array $instrumentIds = null;

    /** @var string[]|null */
    public ?array $styleIds = null;

    /** @var Pricing[]|null */
    #[Assert\Valid]
    public ?array $pricing = null;

    /** @var Availability[]|null */
    #[Assert\Valid]
    public ?array $availability = null;

    /** @var Package[]|null */
    #[Assert\Valid]
    public ?array $packages = null;

    /** @var SocialLink[]|null */
    #[Assert\Valid]
    public ?array $socialLinks = null;

    /**
     * « Ce que tu vas apprendre » (#1312).
     *
     * @var LevelGoal[]|null
     */
    #[Assert\Valid]
    public ?array $levelGoals = null;

    /**
     * « Morceaux travaillés » (#1312).
     *
     * @var Song[]|null
     */
    #[Assert\Count(max: 8, maxMessage: 'profile.teacher.song.too_many')]
    #[Assert\Valid]
    public ?array $songs = null;
}

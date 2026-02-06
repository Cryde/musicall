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
    #[Assert\NotBlank(message: 'La présentation est obligatoire', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Les années d\'expérience sont obligatoires', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    #[Assert\PositiveOrZero(message: 'Les années d\'expérience doivent être positives', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    #[Assert\LessThanOrEqual(value: 70, message: 'Les années d\'expérience ne peuvent pas dépasser {{ compared_value }} ans', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
    public ?int $yearsOfExperience = null;

    /** @var string[] */
    #[Assert\All([
        new Assert\Choice(
            choices: ['beginner', 'intermediate', 'advanced'],
            message: 'Niveau invalide : {{ value }}',
        ),
    ])]
    public array $studentLevels = [];

    /** @var string[] */
    #[Assert\All([
        new Assert\Choice(
            choices: ['children', 'teenagers', 'adults', 'seniors'],
            message: 'Tranche d\'âge invalide : {{ value }}',
        ),
    ])]
    public array $ageGroups = [];

    public ?string $courseTitle = null;

    public bool $offersTrial = false;

    #[Assert\PositiveOrZero(message: 'Le prix du cours d\'essai doit être positif ou nul')]
    public ?int $trialPrice = null;

    /** @var Location[] */
    #[Assert\Valid]
    public $locations = [];

    /** @var string[] */
    #[Assert\Count(min: 1, minMessage: 'Vous devez sélectionner au moins un instrument', groups: ['teacher_profile_create', 'teacher_profile_edit'])]
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

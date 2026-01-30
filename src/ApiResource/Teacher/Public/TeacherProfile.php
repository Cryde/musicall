<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Teacher\TeacherProfileProvider;

#[Get(
    uriTemplate: '/user/profile/{username}/teacher',
    openapi: new Operation(tags: ['Teacher Profile']),
    name: 'api_teacher_profile_get',
    provider: TeacherProfileProvider::class,
)]
class TeacherProfile
{
    #[ApiProperty(identifier: true)]
    public string $username;

    public string $userId;

    public ?string $profilePictureUrl = null;

    public ?string $description = null;

    public ?int $yearsOfExperience = null;

    /** @var string[] */
    public array $studentLevels = [];

    /** @var string[] */
    public array $ageGroups = [];

    public ?string $courseTitle = null;

    public bool $offersTrial = false;

    public ?int $trialPrice = null;

    /** @var TeacherProfileLocation[] */
    #[ApiProperty(genId: false)]
    public array $locations = [];

    /** @var TeacherProfileInstrument[] */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    /** @var TeacherProfileStyle[] */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** @var \App\ApiResource\Teacher\TeacherProfileMedia[] */
    #[ApiProperty(genId: false)]
    public array $media = [];

    /** @var TeacherProfilePricing[] */
    #[ApiProperty(genId: false)]
    public array $pricing = [];

    /** @var TeacherProfileAvailability[] */
    #[ApiProperty(genId: false)]
    public array $availability = [];

    /** @var TeacherProfilePackage[] */
    #[ApiProperty(genId: false)]
    public array $packages = [];

    /** @var TeacherProfileSocialLink[] */
    #[ApiProperty(genId: false)]
    public array $socialLinks = [];

    public \DateTimeImmutable $creationDatetime;

    public ?\DateTimeImmutable $updateDatetime = null;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Teacher\TeacherProfileCreateProcessor;
use App\State\Processor\Teacher\TeacherProfileDeleteProcessor;
use App\State\Processor\Teacher\TeacherProfileEditProcessor;
use App\State\Provider\Teacher\TeacherProfileEditProvider;

#[Get(
    uriTemplate: '/user/teacher-profile',
    openapi: new Operation(tags: ['Teacher Profile']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_teacher_profile_edit_get',
    provider: TeacherProfileEditProvider::class,
)]
#[Post(
    uriTemplate: '/user/teacher-profile',
    openapi: new Operation(tags: ['Teacher Profile']),
    security: 'is_granted("ROLE_USER")',
    validationContext: ['groups' => ['Default', 'teacher_profile_create']],
    input: TeacherProfileInput::class,
    name: 'api_teacher_profile_create',
    processor: TeacherProfileCreateProcessor::class,
)]
#[Patch(
    uriTemplate: '/user/teacher-profile',
    openapi: new Operation(tags: ['Teacher Profile']),
    security: 'is_granted("ROLE_USER")',
    validationContext: ['groups' => ['Default', 'teacher_profile_edit']],
    input: TeacherProfileInput::class,
    name: 'api_teacher_profile_edit',
    provider: TeacherProfileEditProvider::class,
    processor: TeacherProfileEditProcessor::class,
)]
#[Delete(
    uriTemplate: '/user/teacher-profile',
    openapi: new Operation(tags: ['Teacher Profile']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_teacher_profile_delete',
    provider: TeacherProfileEditProvider::class,
    processor: TeacherProfileDeleteProcessor::class,
)]
class TeacherProfileOutput
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

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
}

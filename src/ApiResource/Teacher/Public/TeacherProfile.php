<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Musician\MusicianProfileBand;
use App\ApiResource\Musician\PublicMusicianProfileInstrument;
use App\ApiResource\Teacher\TeacherProfileMedia;
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

    public string $displayName;

    public \DateTimeInterface $memberSince;

    /** Always true for a visitor; false only for its owner, who still sees their private lessons page (#1317). */
    public bool $isPublic = true;

    /**
     * The member's instruments as a musician, with their level, for « Le musicien derrière le prof ». Empty without a
     * musician profile.
     *
     * @var PublicMusicianProfileInstrument[]
     */
    #[ApiProperty(genId: false)]
    public array $musicianInstruments = [];

    /**
     * The bands the member plays in now (#1313), for « Le musicien derrière le prof ». Empty without a musician profile.
     *
     * @var MusicianProfileBand[]
     */
    #[ApiProperty(genId: false)]
    public array $musicianBands = [];

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

    /** @var TeacherProfileMedia[] */
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

    /**
     * « Ce que tu vas apprendre » (#1312), for the levels still accepted, in teaching order.
     *
     * @var list<array{level: string, title: string|null, points: list<string>}>
     */
    public array $levelGoals = [];

    /**
     * « Morceaux travaillés » (#1312), in the teacher's order.
     *
     * @var list<array{artist: string|null, title: string}>
     */
    public array $songs = [];

    public \DateTimeImmutable $creationDatetime;

    public ?\DateTimeImmutable $updateDatetime = null;
}

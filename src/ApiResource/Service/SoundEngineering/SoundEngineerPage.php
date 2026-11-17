<?php

declare(strict_types=1);

namespace App\ApiResource\Service\SoundEngineering;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Musician\PublicMusicianProfileInstrument;
use App\ApiResource\Service\View\ServiceAudioView;
use App\ApiResource\Service\View\ServiceOfferView;
use App\ApiResource\Service\View\ServicePlaceView;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\State\Provider\Service\SoundEngineerPageProvider;

/** A sound engineer's page (#1410), `/u/{username}/sound-engineer`. */
#[Get(
    uriTemplate: '/user/profile/{username}/sound-engineer',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_sound_engineer_page_get',
    provider: SoundEngineerPageProvider::class,
)]
class SoundEngineerPage
{
    #[ApiProperty(identifier: true)]
    public string $username;

    /** The service's id, which its sounds and every later service endpoint are keyed by. */
    #[ApiProperty(identifier: false)]
    public string $id;

    public string $userId;

    public string $displayName;

    public \DateTimeInterface $memberSince;

    /** Always true for a visitor; false only for its owner, who still sees their private page (#1317). */
    public bool $isPublic = true;

    /** Always `published` for a visitor; `draft` only for its owner (#1406). */
    public string $status = 'published';

    public ?string $profilePictureUrl = null;

    /** « Qui est Julien ? ». */
    public ?string $bio = null;

    /**
     * The member's instruments as a musician, for « Guitariste, membre depuis 2014 ».
     *
     * @var PublicMusicianProfileInstrument[]
     */
    #[ApiProperty(genId: false)]
    public array $musicianInstruments = [];

    public ?string $headline = null;

    public ?string $description = null;

    public ?int $yearsOfExperience = null;

    /** @var list<string> SoundEngineeringSpeciality values */
    public array $specialities = [];

    /** @var TeacherProfileStyle[] */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** @var ServicePlaceView[] */
    #[ApiProperty(genId: false)]
    public array $places = [];

    /** @var ServiceOfferView[] */
    #[ApiProperty(genId: false)]
    public array $offers = [];

    public ?int $turnaroundDays = null;

    public ?int $revisionsIncluded = null;

    /** @var list<string> DeliveryFormat values */
    public array $formats = [];

    /** @var list<string> */
    public array $gear = [];

    /** @var TeacherProfileMedia[] */
    #[ApiProperty(genId: false)]
    public array $media = [];

    /**
     * « Avant, après », before first.
     *
     * @var ServiceAudioView[]
     */
    #[ApiProperty(genId: false)]
    public array $beforeAfter = [];
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Musician\PublicMusicianProfileInstrument;
use App\ApiResource\Service\View\ServiceOfferView;
use App\ApiResource\Service\View\ServicePlaceView;
use App\ApiResource\Service\View\ServiceWorkView;
use App\ApiResource\Teacher\Public\TeacherProfileStyle;
use App\ApiResource\Teacher\TeacherProfileMedia;

/** What every kind's page shows (#1410, #1411): the member, and their service. Each kind's page adds its details. */
abstract class ServicePage
{
    #[ApiProperty(identifier: true)]
    public string $username;

    /** The service's id, which every later service endpoint is keyed by. */
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

    /** Counted from `experienceStartYear` on every read (#1453). */
    public ?int $yearsOfExperience = null;

    public ?int $experienceStartYear = null;

    /** @var list<string> the kind's speciality values */
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

    /** @var TeacherProfileMedia[] */
    #[ApiProperty(genId: false)]
    public array $media = [];

    /**
     * « Réalisations » (#1407), in the owner's order.
     *
     * @var ServiceWorkView[]
     */
    #[ApiProperty(genId: false)]
    public array $works = [];
}

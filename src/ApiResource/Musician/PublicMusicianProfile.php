<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\User\Profile\PublicProfileAnnounce;
use App\State\Provider\Musician\MusicianProfileProvider;

#[Get(
    uriTemplate: '/user/profile/{username}/musician',
    openapi: new Operation(tags: ['Musician Profile']),
    name: 'api_musician_profile_get',
    provider: MusicianProfileProvider::class,
)]
class PublicMusicianProfile
{
    #[ApiProperty(identifier: true)]
    public string $username;

    public string $userId;

    public ?string $profilePictureUrl = null;

    public ?string $availabilityStatus = null;

    public ?string $availabilityStatusLabel = null;

    /** @var PublicMusicianProfileInstrument[] */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    /** @var PublicMusicianProfileStyle[] */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** @var PublicProfileAnnounce[] */
    #[ApiProperty(genId: false)]
    public array $musicianAnnounces = [];

    /** @var MusicianProfileMedia[] */
    #[ApiProperty(genId: false)]
    public array $media = [];

    public \DateTimeImmutable $creationDatetime;

    public ?\DateTimeImmutable $updateDatetime = null;
}

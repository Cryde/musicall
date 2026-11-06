<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Musician\MusicianProfileCreateProcessor;
use App\State\Processor\Musician\MusicianProfileDeleteProcessor;
use App\State\Processor\Musician\MusicianProfileEditProcessor;
use App\State\Provider\Musician\MusicianProfileEditProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[Get(
    uriTemplate: '/user/musician-profile',
    openapi: new Operation(tags: ['Musician Profile']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_musician_profile_edit_get',
    provider: MusicianProfileEditProvider::class,
)]
#[Post(
    uriTemplate: '/user/musician-profile',
    openapi: new Operation(tags: ['Musician Profile']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_musician_profile_create',
    processor: MusicianProfileCreateProcessor::class,
)]
#[Patch(
    uriTemplate: '/user/musician-profile',
    openapi: new Operation(tags: ['Musician Profile']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_musician_profile_edit',
    provider: MusicianProfileEditProvider::class,
    processor: MusicianProfileEditProcessor::class,
)]
#[Delete(
    uriTemplate: '/user/musician-profile',
    openapi: new Operation(tags: ['Musician Profile']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_musician_profile_delete',
    provider: MusicianProfileEditProvider::class,
    processor: MusicianProfileDeleteProcessor::class,
)]
class MusicianProfileEdit
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $availabilityStatus = null;

    public ?string $availabilityStatusLabel = null;

    /**
     * Null when left out: the PATCH has no URI variable, so nothing is merged from stored state (#1325).
     *
     * @var MusicianProfileEditInstrument[]|null
     */
    #[ApiProperty(genId: false)]
    public ?array $instruments = null;

    /** @var string[]|null Input only, null when left out, as instruments */
    public ?array $styleIds = null;

    /** @var MusicianProfileEditStyle[] Used for output/display */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /**
     * Null when the request leaves it out: the stored bands stay, as the Android app does not send them.
     *
     * @var MusicianProfileBand[]|null
     */
    #[ApiProperty(genId: false)]
    #[Assert\Count(max: 5, maxMessage: 'profile.musician.band.too_many')]
    #[Assert\Valid]
    public ?array $bands = null;
}

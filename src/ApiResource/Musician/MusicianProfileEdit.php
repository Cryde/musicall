<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Musician\MusicianProfileCreateProcessor;
use App\State\Processor\Musician\MusicianProfileEditProcessor;
use App\State\Provider\Musician\MusicianProfileEditProvider;

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
class MusicianProfileEdit
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $availabilityStatus = null;

    public ?string $availabilityStatusLabel = null;

    /** @var MusicianProfileEditInstrument[] */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    /** @var string[] Used for input only */
    public array $styleIds = [];

    /** @var MusicianProfileEditStyle[] Used for output/display */
    #[ApiProperty(genId: false)]
    public array $styles = [];
}

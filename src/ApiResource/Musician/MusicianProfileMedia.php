<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Musician\Profile\Media;
use App\State\Processor\Musician\MusicianProfileMediaCreateProcessor;
use App\State\Processor\Musician\MusicianProfileMediaDeleteProcessor;
use App\State\Provider\Musician\MusicianProfileMediaProvider;

#[GetCollection(
    uriTemplate: '/user/musician-profile/media',
    openapi: new Operation(tags: ['Musician Profile Media']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_musician_profile_media_get_collection',
    provider: MusicianProfileMediaProvider::class,
)]
#[Post(
    uriTemplate: '/user/musician-profile/media',
    openapi: new Operation(tags: ['Musician Profile Media']),
    security: 'is_granted("ROLE_USER")',
    input: Media::class,
    name: 'api_musician_profile_media_create',
    processor: MusicianProfileMediaCreateProcessor::class,
)]
#[Delete(
    uriTemplate: '/user/musician-profile/media/{id}',
    openapi: new Operation(tags: ['Musician Profile Media']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_musician_profile_media_delete',
    provider: MusicianProfileMediaProvider::class,
    processor: MusicianProfileMediaDeleteProcessor::class,
)]
class MusicianProfileMedia
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $platform = null;

    public ?string $platformLabel = null;

    public ?string $url = null;

    public ?string $embedId = null;

    public ?string $title = null;

    public ?string $thumbnailUrl = null;

    public int $position = 0;
}

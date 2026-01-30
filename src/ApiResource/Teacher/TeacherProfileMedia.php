<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Teacher\Profile\Media;
use App\State\Processor\Teacher\TeacherProfileMediaCreateProcessor;
use App\State\Processor\Teacher\TeacherProfileMediaDeleteProcessor;
use App\State\Provider\Teacher\TeacherProfileMediaProvider;

#[GetCollection(
    uriTemplate: '/user/teacher-profile/media',
    openapi: new Operation(tags: ['Teacher Profile Media']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_teacher_profile_media_get_collection',
    provider: TeacherProfileMediaProvider::class,
)]
#[Post(
    uriTemplate: '/user/teacher-profile/media',
    openapi: new Operation(tags: ['Teacher Profile Media']),
    security: 'is_granted("ROLE_USER")',
    input: Media::class,
    name: 'api_teacher_profile_media_create',
    processor: TeacherProfileMediaCreateProcessor::class,
)]
#[Delete(
    uriTemplate: '/user/teacher-profile/media/{id}',
    openapi: new Operation(tags: ['Teacher Profile Media']),
    security: 'is_granted("ROLE_USER")',
    name: 'api_teacher_profile_media_delete',
    provider: TeacherProfileMediaProvider::class,
    processor: TeacherProfileMediaDeleteProcessor::class,
)]
class TeacherProfileMedia
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $platform = null;

    public ?string $url = null;

    public ?string $embedId = null;

    public ?string $title = null;

    public ?string $thumbnailUrl = null;

    public int $position = 0;
}

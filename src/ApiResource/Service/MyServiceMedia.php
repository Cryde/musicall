<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\State\Processor\Service\MyServiceMediaCreateProcessor;
use App\State\Processor\Service\MyServiceMediaDeleteProcessor;

/** The YouTube, SoundCloud or Spotify embeds of a member's own service, by its id (#1410). */
#[Post(
    uriTemplate: '/user/services/{id}/media',
    uriVariables: ['id' => new Link(fromClass: self::class, identifiers: ['id'])],
    requirements: ['id' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    security: 'is_granted("ROLE_USER")',
    input: MyServiceMediaInput::class,
    output: TeacherProfileMedia::class,
    read: false,
    name: 'api_user_services_media_create',
    processor: MyServiceMediaCreateProcessor::class,
)]
#[Delete(
    uriTemplate: '/user/services/{id}/media/{mediaId}',
    uriVariables: [
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
        'mediaId' => new Link(fromClass: self::class, identifiers: ['mediaId']),
    ],
    requirements: ['id' => '[0-9a-fA-F\-]{36}', 'mediaId' => '[0-9a-fA-F\-]{36}'],
    openapi: new Operation(tags: ['Service']),
    security: 'is_granted("ROLE_USER")',
    read: false,
    name: 'api_user_services_media_delete',
    processor: MyServiceMediaDeleteProcessor::class,
)]
class MyServiceMedia
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $mediaId;
}

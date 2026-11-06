<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Profile\ProfilePostPhotoProvider;

/** A wall photo, streamed only to who may see the wall (#1316): the storage itself is private. */
#[ApiResource(
    shortName: 'ProfilePostPhotoFile',
    operations: [
        new Get(
            uriTemplate: '/user/profile/{username}/posts/{postId}/photos/{id}',
            uriVariables: [
                'username' => new Link(fromClass: self::class, identifiers: ['username']),
                'postId' => new Link(fromClass: self::class, identifiers: ['postId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['username' => '[^/]+'],
            openapi: new Operation(tags: ['Profile Wall']),
            output: false,
            name: 'api_profile_post_photos_get',
            provider: ProfilePostPhotoProvider::class,
        ),
    ],
)]
class ProfilePostPhotoFile
{
    #[ApiProperty(identifier: true)]
    public string $username;

    #[ApiProperty(identifier: true)]
    public string $postId;

    #[ApiProperty(identifier: true)]
    public string $id;
}

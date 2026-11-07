<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Profile\ProfilePost;
use App\State\Provider\Profile\ProfilePostLikesProvider;
use Symfony\Component\Routing\Requirement\Requirement;

/** Someone who liked a wall post (#1316), named as anyone else reads them. */
#[ApiResource(
    shortName: 'ProfilePostLiker',
    operations: [
        // Who liked a post, newest first. Read like the wall.
        new GetCollection(
            uriTemplate: '/user/profile-posts/{postId}/likes',
            uriVariables: [
                'postId' => new Link(fromClass: ProfilePost::class, identifiers: ['id']),
            ],
            requirements: ['postId' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            paginationEnabled: false,
            name: 'api_profile_post_likes_list',
            provider: ProfilePostLikesProvider::class,
        ),
        // Not served, only the address of each member, declared so a username may hold a dot (see FeaturedTeacher).
        new NotExposed(
            uriTemplate: '/profile_post_likers/{username}',
            requirements: ['username' => '[^/]+'],
        ),
    ],
)]
class ProfilePostLiker
{
    #[ApiProperty(identifier: true)]
    public string $username;

    public string $displayName;

    public ?string $profilePictureUrl = null;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Profile\ProfilePost;
use App\Service\Profile\Wall\DatedPosition;
use App\State\Provider\Profile\ProfilePostLikesProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/** Someone who liked a wall post (#1316), named as anyone else reads them. */
#[ApiResource(
    shortName: 'ProfilePostLiker',
    operations: [
        // Who liked a post, PAGE_SIZE at a time, newest first (#1331): `before` is the last one's `cursor`, and a
        // shorter page is the last one. Read like the wall.
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
            parameters: [
                'before' => new QueryParameter(key: 'before', constraints: [new Assert\Sequentially([
                    new Assert\Type(type: 'string', message: 'profile.wall.likers_cursor_invalid'),
                    new Assert\Regex(pattern: DatedPosition::PATTERN, message: 'profile.wall.likers_cursor_invalid'),
                ])]),
            ],
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
    public const int PAGE_SIZE = 20;

    #[ApiProperty(identifier: true)]
    public string $username;

    public string $displayName;

    public ?string $profilePictureUrl = null;

    /** This like's place in the list: the next page starts after it. */
    public string $cursor;
}

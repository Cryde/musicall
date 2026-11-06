<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Profile\ProfilePostLikeDeleteProcessor;
use App\State\Processor\Profile\ProfilePostLikePostProcessor;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Liking a wall post and taking the like back (#1316). Two operations rather than a toggle, as for chat
 * reactions: a double tap likes once instead of undoing itself.
 */
#[ApiResource(
    shortName: 'ProfilePostLike',
    operations: [
        new Post(
            uriTemplate: '/user/profile-posts/{postId}/like',
            uriVariables: ['postId' => new Link(fromClass: self::class, identifiers: ['postId'])],
            requirements: ['postId' => Requirement::UUID],
            status: 200,
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            input: false,
            read: false,
            name: 'api_profile_post_like_post',
            processor: ProfilePostLikePostProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/profile-posts/{postId}/like',
            uriVariables: ['postId' => new Link(fromClass: self::class, identifiers: ['postId'])],
            requirements: ['postId' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            read: false,
            name: 'api_profile_post_like_delete',
            processor: ProfilePostLikeDeleteProcessor::class,
        ),
    ],
)]
class ProfilePostLikeState
{
    #[ApiProperty(identifier: true)]
    public string $postId;

    public int $likeCount = 0;

    public bool $hasLiked = false;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Profile\ProfilePostCommentLikeDeleteProcessor;
use App\State\Processor\Profile\ProfilePostCommentLikePostProcessor;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Liking a wall post comment or reply and taking the like back (#1331), as for a post: two operations rather than
 * a toggle, so a double tap likes once instead of undoing itself.
 */
#[ApiResource(
    shortName: 'ProfilePostCommentLike',
    operations: [
        new Post(
            uriTemplate: '/user/profile-post-comments/{commentId}/like',
            uriVariables: ['commentId' => new Link(fromClass: self::class, identifiers: ['commentId'])],
            requirements: ['commentId' => Requirement::UUID],
            status: 200,
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            input: false,
            read: false,
            name: 'api_profile_post_comment_like_post',
            processor: ProfilePostCommentLikePostProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/profile-post-comments/{commentId}/like',
            uriVariables: ['commentId' => new Link(fromClass: self::class, identifiers: ['commentId'])],
            requirements: ['commentId' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            read: false,
            name: 'api_profile_post_comment_like_delete',
            processor: ProfilePostCommentLikeDeleteProcessor::class,
        ),
    ],
)]
class ProfilePostCommentLikeState
{
    #[ApiProperty(identifier: true)]
    public string $commentId;

    public int $likeCount = 0;

    public bool $hasLiked = false;
}

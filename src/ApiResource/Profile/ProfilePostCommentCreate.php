<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Profile\ProfilePostComment;
use App\State\Processor\Profile\ProfilePostCommentCreateProcessor;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/** A comment on a wall post, or a reply to one of its comments (#1316). */
#[Post(
    uriTemplate: '/user/profile-posts/{postId}/comments',
    uriVariables: ['postId' => new Link(fromClass: self::class, identifiers: ['postId'])],
    requirements: ['postId' => Requirement::UUID],
    openapi: new Operation(tags: ['Profile Wall']),
    security: 'is_granted("ROLE_USER")',
    read: false,
    output: ProfilePostCommentResource::class,
    name: 'api_profile_post_comments_post',
    processor: ProfilePostCommentCreateProcessor::class,
)]
class ProfilePostCommentCreate
{
    #[Assert\NotBlank(message: 'profile.wall.comment_empty', normalizer: 'trim')]
    #[Assert\Length(max: ProfilePostComment::MAX_LENGTH, maxMessage: 'profile.wall.comment_too_long')]
    public string $content = '';

    /** The comment answered. A reply cannot itself be answered: one level only. */
    #[Assert\Uuid(message: 'profile.wall.comment_not_found')]
    public ?string $parentId = null;
}

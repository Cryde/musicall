<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Profile\ProfilePost;
use App\Service\Profile\Wall\CommentCursor;
use App\Service\Profile\Wall\CommentOrder;
use App\State\Processor\Profile\ProfilePostCommentDeleteProcessor;
use App\State\Provider\Profile\ProfilePostCommentProvider;
use App\State\Provider\Profile\ProfilePostCommentsProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/** A comment on a wall post, or a reply to one (#1316). Its author or the wall's owner may delete it. */
#[ApiResource(
    shortName: 'ProfilePostComment',
    operations: [
        // A page of a post's comments (#1331): CommentThread::PAGE_SIZE top-level comments in `order` (newest first
        // by default), each followed by its replies oldest first, which carry their `parent_id`. `before` is the
        // last top-level comment's `cursor`, made for the same order, and a page with fewer top-level comments is
        // the last one. `around` makes the first page reach down to a comment, see CommentThread. Read like the wall.
        new GetCollection(
            uriTemplate: '/user/profile-posts/{postId}/comments',
            uriVariables: [
                'postId' => new Link(fromClass: ProfilePost::class, identifiers: ['id']),
            ],
            requirements: ['postId' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            paginationEnabled: false,
            name: 'api_profile_post_comments_list',
            provider: ProfilePostCommentsProvider::class,
            parameters: [
                'before' => new QueryParameter(key: 'before', constraints: [new Assert\Sequentially([
                    new Assert\Type(type: 'string', message: 'profile.wall.comments_cursor_invalid'),
                    new Assert\Regex(pattern: CommentCursor::PATTERN, message: 'profile.wall.comments_cursor_invalid'),
                ])]),
                'order' => new QueryParameter(key: 'order', hydra: false, constraints: [new Assert\Sequentially([
                    new Assert\Type(type: 'string', message: 'profile.wall.comments_order_invalid'),
                    new Assert\Choice(callback: [CommentOrder::class, 'values'], message: 'profile.wall.comments_order_invalid'),
                ])]),
                'around' => new QueryParameter(key: 'around', constraints: [new Assert\Sequentially([
                    new Assert\Type(type: 'string', message: 'profile.wall.comment_id_invalid'),
                    new Assert\Regex(pattern: self::COMMENT_ID_PATTERN, message: 'profile.wall.comment_id_invalid'),
                ])]),
            ],
        ),
        new Get(
            uriTemplate: '/user/profile-post-comments/{id}',
            requirements: ['id' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            name: 'api_profile_post_comments_get',
            provider: ProfilePostCommentProvider::class,
        ),
        new Delete(
            uriTemplate: '/user/profile-post-comments/{id}',
            requirements: ['id' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            read: false,
            name: 'api_profile_post_comments_delete',
            processor: ProfilePostCommentDeleteProcessor::class,
        ),
    ],
)]
class ProfilePostCommentResource
{
    /** Anchored with \z: `$` would also take a trailing newline. */
    public const string COMMENT_ID_PATTERN = '/^' . Requirement::UUID . '\z/';

    #[ApiProperty(identifier: true)]
    public string $id;

    public ?string $parentId = null;

    #[ApiProperty(genId: false)]
    public ProfilePostCommentAuthor $author;

    public string $content;

    public \DateTimeInterface $creationDatetime;

    public bool $canDelete = false;

    /** A top-level comment's place in the thread, in a list only: the next page starts after it. */
    public ?string $cursor = null;
}

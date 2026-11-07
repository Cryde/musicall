<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Profile\ProfilePost;
use App\State\Processor\Profile\ProfilePostCommentDeleteProcessor;
use App\State\Provider\Profile\ProfilePostCommentProvider;
use App\State\Provider\Profile\ProfilePostCommentsProvider;
use Symfony\Component\Routing\Requirement\Requirement;

/** A comment on a wall post, or a reply to one (#1316). Its author or the wall's owner may delete it. */
#[ApiResource(
    shortName: 'ProfilePostComment',
    operations: [
        // A post's comments, oldest first, replies carrying their `parent_id`. Read like the wall.
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
    #[ApiProperty(identifier: true)]
    public string $id;

    public ?string $parentId = null;

    #[ApiProperty(genId: false)]
    public ProfilePostCommentAuthor $author;

    public string $content;

    public \DateTimeInterface $creationDatetime;

    public bool $canDelete = false;
}

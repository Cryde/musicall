<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Profile\ProfilePostCommentsProvider;
use Symfony\Component\Routing\Requirement\Requirement;

/** A wall post's comments, oldest first, replies carrying their `parent_id` (#1316). Read like the wall. */
#[ApiResource(
    shortName: 'ProfilePostComments',
    operations: [
        new Get(
            uriTemplate: '/user/profile-posts/{postId}/comments',
            requirements: ['postId' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            name: 'api_profile_post_comments_list',
            provider: ProfilePostCommentsProvider::class,
        ),
    ],
)]
class ProfilePostComments
{
    #[ApiProperty(identifier: true)]
    public string $postId;

    /** @var ProfilePostCommentResource[] */
    #[ApiProperty(genId: false)]
    public array $comments = [];
}

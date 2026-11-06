<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Profile\ProfilePostLikesProvider;
use Symfony\Component\Routing\Requirement\Requirement;

/** Who liked a wall post, newest first (#1316). Read like the wall. */
#[ApiResource(
    shortName: 'ProfilePostLikes',
    operations: [
        new Get(
            uriTemplate: '/user/profile-posts/{postId}/likes',
            requirements: ['postId' => Requirement::UUID],
            openapi: new Operation(tags: ['Profile Wall']),
            name: 'api_profile_post_likes_list',
            provider: ProfilePostLikesProvider::class,
        ),
    ],
)]
class ProfilePostLikes
{
    #[ApiProperty(identifier: true)]
    public string $postId;

    /** @var ProfilePostAuthor[] */
    #[ApiProperty(genId: false)]
    public array $likers = [];
}

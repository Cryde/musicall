<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Profile\ProfileWallProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A page of a member's wall (#1316): the pinned post on the first page, then the others newest first.
 * `before` is the last post the client holds, as the band chat window does: a post id carries no order.
 */
#[ApiResource(
    shortName: 'ProfileWall',
    operations: [
        new Get(
            uriTemplate: '/user/profile/{username}/wall',
            requirements: ['username' => '[^/]+'],
            openapi: new Operation(tags: ['Profile Wall']),
            name: 'api_profile_wall_get',
            provider: ProfileWallProvider::class,
            parameters: [
                'before' => new QueryParameter(key: 'before', constraints: [new Assert\Uuid()]),
            ],
        ),
    ],
)]
class ProfileWall
{
    public const int PAGE_SIZE = 10;

    #[ApiProperty(identifier: true)]
    public string $username;

    #[ApiProperty(genId: false)]
    public ?ProfilePostResource $pinnedPost = null;

    /** @var ProfilePostResource[] */
    #[ApiProperty(genId: false)]
    public array $posts = [];

    public bool $hasMore = false;
}

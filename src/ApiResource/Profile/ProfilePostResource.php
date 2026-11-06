<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Profile\ProfilePostDeleteProcessor;
use App\State\Processor\Profile\ProfilePostEditProcessor;
use App\State\Provider\Profile\ProfilePostEditProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A wall post as everyone reads it (#1316), and what its author edits: the text and the pin. The PATCH has a
 * URI variable, so the provider fills both and a field left out keeps its value (#1327).
 */
#[ApiResource(
    shortName: 'ProfilePost',
    operations: [
        // Your own post, read back as the edit form needs it. Others read posts through the wall.
        new Get(
            uriTemplate: '/user/profile-posts/{id}',
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            name: 'api_profile_posts_get',
            provider: ProfilePostEditProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/profile-posts/{id}',
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            name: 'api_profile_posts_patch',
            provider: ProfilePostEditProvider::class,
            processor: ProfilePostEditProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/profile-posts/{id}',
            openapi: new Operation(tags: ['Profile Wall']),
            security: 'is_granted("ROLE_USER")',
            name: 'api_profile_posts_delete',
            provider: ProfilePostEditProvider::class,
            processor: ProfilePostDeleteProcessor::class,
        ),
    ],
)]
class ProfilePostResource
{
    #[ApiProperty(identifier: true, writable: false)]
    public string $id;

    #[ApiProperty(genId: false, writable: false)]
    public ProfilePostAuthor $author;

    /** Only its photos or its link let a post go without text. */
    #[Assert\When(
        expression: 'this.photos == [] and this.media === null',
        constraints: [new Assert\NotBlank(message: 'profile.wall.post_empty', normalizer: 'trim')],
    )]
    #[Assert\Length(max: 2000, maxMessage: 'profile.wall.content_too_long')]
    public ?string $content = null;

    /** @var ProfilePostPhoto[] */
    #[ApiProperty(genId: false, writable: false)]
    public array $photos = [];

    #[ApiProperty(genId: false, writable: false)]
    public ?ProfilePostMedia $media = null;

    public bool $isPinned = false;

    #[ApiProperty(writable: false)]
    public \DateTimeInterface $creationDatetime;

    #[ApiProperty(writable: false)]
    public ?\DateTimeInterface $updateDatetime = null;

    #[ApiProperty(writable: false)]
    public bool $canEdit = false;

    #[ApiProperty(writable: false)]
    public int $likeCount = 0;

    #[ApiProperty(writable: false)]
    public bool $hasLiked = false;

    #[ApiProperty(writable: false)]
    public int $commentCount = 0;
}

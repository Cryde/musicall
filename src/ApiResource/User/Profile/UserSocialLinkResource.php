<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Profile\UserSocialLinkDeleteProcessor;
use App\State\Processor\User\Profile\UserSocialLinkPostProcessor;
use App\State\Provider\User\Profile\UserSocialLinkCollectionProvider;
use App\State\Provider\User\Profile\UserSocialLinkDeleteProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user/profile/social-links',
            openapi: new Operation(tags: ['Profile']),
            paginationEnabled: false,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_social_links_get_collection',
            provider: UserSocialLinkCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/user/profile/social-links',
            openapi: new Operation(tags: ['Profile']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_social_links_post',
            processor: UserSocialLinkPostProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/profile/social-links/{id}',
            openapi: new Operation(tags: ['Profile']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_social_links_delete',
            provider: UserSocialLinkDeleteProvider::class,
            processor: UserSocialLinkDeleteProcessor::class,
        ),
    ]
)]
class UserSocialLinkResource
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[Assert\NotBlank(message: 'La plateforme est requise')]
    public string $platform;

    public ?string $platformLabel = null;

    #[Assert\NotBlank(message: 'L\'URL est requise')]
    #[Assert\Url(message: 'L\'URL n\'est pas valide', requireTld: true)]
    #[Assert\Length(max: 500, maxMessage: 'L\'URL ne doit pas dépasser {{ limit }} caractères')]
    public string $url;
}

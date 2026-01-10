<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Profile\UserProfileEditProcessor;
use App\State\Provider\User\Profile\UserProfileEditProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/profile',
            openapi: new Operation(tags: ['Profile']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_profile_get',
            provider: UserProfileEditProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/profile',
            openapi: new Operation(tags: ['Profile']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_profile_edit',
            provider: UserProfileEditProvider::class,
            processor: UserProfileEditProcessor::class,
        ),
    ]
)]
class UserProfileEdit
{
    #[Assert\Length(max: 2000, maxMessage: 'La bio ne doit pas dépasser {{ limit }} caractères')]
    public ?string $bio = null;

    #[Assert\Length(max: 255, maxMessage: 'La localisation ne doit pas dépasser {{ limit }} caractères')]
    public ?string $location = null;

    public bool $isPublic = true;

    public ?string $profilePictureUrl = null;

    public ?string $coverPictureUrl = null;
}

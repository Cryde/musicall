<?php

declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\User\UsernameAvailabilityProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/users/username-availability/{username}',
            openapi: new Operation(tags: ['Users']),
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            name: 'api_users_username_availability',
            provider: UsernameAvailabilityProvider::class,
        ),
    ]
)]
class UsernameAvailability
{
    #[ApiProperty(identifier: true)]
    public string $username;
    public bool $available;
}

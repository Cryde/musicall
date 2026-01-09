<?php

declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\User\UserSelfProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/users/self',
            openapi: new Operation(tags: ['Users']),
            normalizationContext: ['skip_null_values' => false],
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            name: 'api_users_get_self',
            provider: UserSelfProvider::class,
        ),
    ]
)]
class UserSelf
{
    #[ApiProperty(identifier: true)]
    public string $id;
    public string $username;
    public string $email;
    /** @var string[] */
    public array $roles;
    /** @var array{small: string}|null */
    public ?array $profilePicture = null;
    public ?\DateTimeImmutable $usernameChangedDatetime = null;
}

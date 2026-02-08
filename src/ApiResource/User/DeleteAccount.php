<?php

declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\DeleteAccountProcessor;
use App\Validator\User\DeleteAccountPasswordValid;

#[DeleteAccountPasswordValid]
#[Post(
    uriTemplate: '/users/delete_account',
    status: 204,
    openapi: new Operation(tags: ['Users']),
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    output: false,
    name: 'api_users_delete_account_post',
    processor: DeleteAccountProcessor::class,
)]
class DeleteAccount
{
    public ?string $password = null;
}

<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\ResetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/reset-password/{token}',
    openapi: new Operation(tags: ['Users']),
    name: 'api_users_reset_password',
    processor: ResetPasswordProcessor::class,
)]
class ResetPassword
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 6, minMessage: 'Minimum 6 caractères')]
    public string $password;
}
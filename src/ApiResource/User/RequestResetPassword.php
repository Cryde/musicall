<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use App\State\Processor\User\RequestResetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/request-reset-password',
    name: 'api_user_request_reset_password',
    processor: RequestResetPasswordProcessor::class,
)]
class RequestResetPassword
{
    #[Assert\NotBlank()]
    public string $login;
}
<?php declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\RequestResetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/request-reset-password',
    openapi: new Operation(tags: ['Users']),
    name: 'api_users_request_reset_password',
    processor: RequestResetPasswordProcessor::class,
)]
class RequestResetPassword
{
    #[Assert\NotBlank()]
    public string $login;
}

<?php declare(strict_types=1);

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
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Minimum 8 caractères')]
    #[Assert\NotCompromisedPassword(skipOnError: true, message: 'Ce mot de passe est présent dans une fuite de données, veuillez en choisir un autre.')]
    public string $password;
}

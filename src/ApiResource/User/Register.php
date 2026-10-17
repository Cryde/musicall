<?php declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\User;
use App\State\Processor\User\RegisterProcessor;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/register',
    openapi: new Operation(tags: ['Users']),
    name: 'api_users_register',
    processor: RegisterProcessor::class,
    // Validated in the processor, after a rate limit: « Cet email est déjà utilisé » says whether an
    // address has an account, so trying addresses must cost something (#1119).
    validate: false,
)]
#[UniqueEntity(fields: ['username'], message: 'user.username.already_taken', entityClass: User::class)]
#[UniqueEntity(fields: ['email'], message: 'user.email.already_used', entityClass: User::class)]
class Register
{
    #[Assert\NotBlank(message: 'user.username.required')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\._]+$/', message: 'user.username.invalid_characters')]
    #[Assert\Length(min: 3, max: 40, minMessage: 'user.username.too_short', maxMessage: 'user.username.too_long')]
    public string $username;
    #[Assert\NotBlank(message: 'user.email.required')]
    #[Assert\Email(message: 'user.email.invalid')]
    public string $email;
    #[Assert\NotBlank(message: 'user.password.required')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'user.register.password_too_short')]
    #[Assert\NotCompromisedPassword(skipOnError: true, message: 'user.password.compromised')]
    public string $password;
}

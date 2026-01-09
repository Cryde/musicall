<?php

declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\ChangeUsernameProcessor;
use App\Validator\User\UsernameChangeThrottle;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/change_username',
    openapi: new Operation(tags: ['Users']),
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    name: 'api_users_change_username_post',
    processor: ChangeUsernameProcessor::class
)]
#[UsernameChangeThrottle]
class ChangeUsername
{
    #[Assert\NotBlank(message: 'Veuillez saisir un nom d\'utilisateur')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\._]+$/', message: 'Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.')]
    #[Assert\Length(min: 3, max: 40, minMessage: 'Le nom d\'utilisateur doit au moins contenir 3 caractères', maxMessage: 'Le nom d\'utilisateur doit contenir maximum 40 caractères')]
    public string $newUsername;
}

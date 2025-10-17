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
)]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà pris', entityClass: User::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé', entityClass: User::class)]
class Register
{
    #[Assert\NotBlank(message: 'Veuillez saisir un nom d\'utilisateur')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\._]+$/', message: 'Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.')]
    #[Assert\Length(min: 3, max: 40, minMessage: 'Le nom d\'utilisateur doit au moins contenir 3 caractères', maxMessage: 'Le nom d\'utilisateur doit contenir maximum 40 caractères')]
    public string $username;
    #[Assert\NotBlank(message: 'Veuillez saisir un email')]
    #[Assert\Email(message: 'Email invalide')]
    public string $email;
    #[Assert\NotBlank(message: 'Veuillez saisir un mot de passe')]
    #[Assert\Length(min: 6, minMessage: 'Le mot de passe doit au moins contenir 6 caractères')]
    public string $password;
}

<?php declare(strict_types=1);

namespace App\ApiResource\User\Block;

use Symfony\Component\Validator\Constraints as Assert;

class UserBlockCreate
{
    #[Assert\NotBlank(message: 'Veuillez préciser l\'utilisateur à bloquer')]
    #[Assert\Length(max: 36, maxMessage: 'Identifiant invalide')]
    public string $userId = '';
}

<?php

namespace App\Entity\User;

use ApiPlatform\Metadata\Post;
use App\State\Processor\User\ChangePasswordProcessor;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/change_password',
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    processor: ChangePasswordProcessor::class
)]
class ChangePassword
{
    #[Assert\NotBlank]
    #[SecurityAssert\UserPassword(message: 'L\'ancien mot de passe est invalide')]
    public string $oldPassword;
    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    #[Assert\NotEqualTo(propertyPath: 'oldPassword')]
    public string $newPassword;

    public function getOldPassword(): string
    {
        return $this->oldPassword;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}

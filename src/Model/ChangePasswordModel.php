<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordModel
{
    #[Assert\NotBlank]
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

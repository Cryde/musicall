<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordModel
{
    /**
     * @Assert\NotBlank()
     * @Assert\Length(min="6", minMessage="Minimum 6 caractères")
     */
    public string $password;

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): ResetPasswordModel
    {
        $this->password = $password;

        return $this;
    }
}
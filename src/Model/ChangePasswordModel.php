<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordModel
{
    /**
     * @var string
     *
     * @Assert\NotBlank()
     */
    public $oldPassword;
    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Length(min="6")
     * @Assert\NotEqualTo(propertyPath="oldPassword")
     */
    public $newPassword;

    /**
     * @return string
     */
    public function getOldPassword(): string
    {
        return $this->oldPassword;
    }

    /**
     * @return string
     */
    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}

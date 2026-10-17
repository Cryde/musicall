<?php

declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DeleteAccountPasswordValid extends Constraint
{
    public string $messageInvalid = 'user.delete_account.password_invalid';
    public string $messageRequired = 'user.delete_account.password_required';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

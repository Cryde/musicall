<?php

declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DeleteAccountPasswordValid extends Constraint
{
    public string $messageInvalid = 'Le mot de passe est invalide';
    public string $messageRequired = 'Le mot de passe est requis';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

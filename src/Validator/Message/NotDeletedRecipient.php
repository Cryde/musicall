<?php

declare(strict_types=1);

namespace App\Validator\Message;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotDeletedRecipient extends Constraint
{
    public string $message = 'Vous ne pouvez pas envoyer un message à un utilisateur supprimé.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

<?php

declare(strict_types=1);

namespace App\Validator\Message;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotDeletedRecipient extends Constraint
{
    public string $message = 'message.recipient.deleted';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

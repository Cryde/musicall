<?php declare(strict_types=1);

namespace App\Validator\Message;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotSelfRecipient extends Constraint
{
    public string $message = 'Vous ne pouvez pas vous envoyer un message à vous-même.';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        $this->message = $message ?? $this->message;
        parent::__construct(null, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

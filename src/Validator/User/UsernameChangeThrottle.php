<?php

declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UsernameChangeThrottle extends Constraint
{
    public string $message = 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.';

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

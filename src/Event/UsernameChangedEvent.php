<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UsernameChangedEvent extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly string $oldUsername,
        public readonly string $newUsername,
        public readonly \DateTimeImmutable $changedAt,
    ) {
    }
}

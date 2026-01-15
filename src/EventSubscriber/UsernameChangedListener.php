<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UsernameChangedEvent;
use App\Service\Mail\Brevo\User\UsernameChangedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class UsernameChangedListener
{
    public function __construct(
        private UsernameChangedEmail $usernameChangedEmail,
    ) {
    }

    public function __invoke(UsernameChangedEvent $event): void
    {
        $this->usernameChangedEmail->send(
            $event->user->getEmail(),
            $event->oldUsername,
            $event->newUsername,
            $event->changedAt,
        );
    }
}

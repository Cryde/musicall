<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserConfirmedEvent;
use App\Service\Mail\Brevo\User\WelcomeEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class UserConfirmedListener
{
    public function __construct(
        private WelcomeEmail $welcomeEmail,
    ) {
    }

    public function __invoke(UserConfirmedEvent $event): void
    {
        $user = $event->user;
        $this->welcomeEmail->send($user->getEmail(), $user->getUsername());
    }
}

<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Service\User\ConfirmRegistrationSender;
use App\Service\User\UserTokenGenerator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class UserRegisteredListener
{
    public function __construct(
        private ConfirmRegistrationSender $confirmRegistration,
        private UserTokenGenerator        $generateConfirmationToken
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $user = $event->user;
        $this->generateConfirmationToken->generate($user);
        $this->confirmRegistration->sendConfirmationEmail($user);
    }
}

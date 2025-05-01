<?php

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Service\User\ConfirmRegistrationSender;
use App\Service\User\UserTokenGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfirmRegistrationSender $confirmRegistration,
        private readonly UserTokenGenerator        $generateConfirmationToken
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::NAME => 'onUserRegistered',
        ];
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $user = $event->getUser();
        $this->generateConfirmationToken->generate($user);
        $this->confirmRegistration->sendConfirmationEmail($user);
    }
}

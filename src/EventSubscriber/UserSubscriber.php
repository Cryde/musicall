<?php

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Service\User\ConfirmRegistrationSender;
use App\Service\User\UserTokenGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    /**
     * @var ConfirmRegistrationSender
     */
    private ConfirmRegistrationSender $confirmRegistration;
    /**
     * @var UserTokenGenerator
     */
    private UserTokenGenerator $generateConfirmationToken;

    public function __construct(
        ConfirmRegistrationSender $confirmRegistration,
        UserTokenGenerator $generateConfirmationToken
    ) {
        $this->confirmRegistration = $confirmRegistration;
        $this->generateConfirmationToken = $generateConfirmationToken;
    }

    public static function getSubscribedEvents()
    {
        return [
            UserRegisteredEvent::NAME => 'onUserRegistered',
        ];
    }

    public function onUserRegistered(UserRegisteredEvent $event)
    {
        $user = $event->getUser();
        $this->generateConfirmationToken->generate($user);
        $this->confirmRegistration->sendConfirmationEmail($user);
    }
}

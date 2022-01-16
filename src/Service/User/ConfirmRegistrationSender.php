<?php

namespace App\Service\User;

use App\Entity\User;
use App\Service\Mail\RegistrationMail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ConfirmRegistrationSender
{
    private RouterInterface $router;
    private RegistrationMail $registrationMail;

    public function __construct(RouterInterface $router, RegistrationMail $registrationMail)
    {
        $this->router = $router;
        $this->registrationMail = $registrationMail;
    }

    public function sendConfirmationEmail(User $user)
    {
        $route = $this->router->generate('app_register_confirm', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->registrationMail->send($user->getEmail(), $user->getUsername(), $route);
    }
}

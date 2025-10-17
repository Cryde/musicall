<?php declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Service\Mail\Brevo\User\ConfirmRegistrationEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ConfirmRegistrationSender
{
    public function __construct(
        private readonly RouterInterface          $router,
        private readonly ConfirmRegistrationEmail $confirmRegistrationEmail
    ) {
    }

    public function sendConfirmationEmail(User $user): void
    {
        $route = $this->router->generate('app_register_confirm', ['token' => $user->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->confirmRegistrationEmail->send($user->getEmail(), $user->getUsername(), $route);
    }
}

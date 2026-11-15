<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\JwtCookieMinter;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UsernameChangeJwtSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly JwtCookieMinter $jwtCookieMinter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($response->getStatusCode() >= 400) {
            return;
        }
        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== 'api_users_change_username_post') {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // A new JWT, carrying the new username as display data
        $this->jwtCookieMinter->attachTo($response, $this->jwtManager->create($user));

        // No new refresh token: ChangeUsernameProcessor moved the existing ones to the new username,
        // the one in this browser's cookie included (#1025).
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class AuthenticationFailureHandler
{
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();

        $target = $exception instanceof CustomUserMessageAccountStatusException
            ? $exception
            : ($exception->getPrevious() instanceof CustomUserMessageAccountStatusException ? $exception->getPrevious() : null);

        if ($target instanceof CustomUserMessageAccountStatusException && $target->getMessageKey() === 'account_not_verified') {
            $messageData = $target->getMessageData();

            $event->setResponse(new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'account_not_verified',
                'email' => $messageData['{{ email }}'] ?? null,
            ], Response::HTTP_UNAUTHORIZED));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UsernameChangeJwtSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private readonly int $refreshTokenTtl,
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

        // Generate new JWT with updated username
        $jwt = $this->jwtManager->create($user);

        // Split JWT into header.payload and signature
        $parts = explode('.', $jwt);
        $headerPayload = $parts[0] . '.' . $parts[1];
        $signature = $parts[2];

        // Set cookies matching the lexik_jwt configuration
        $response->headers->setCookie(
            Cookie::create('jwt_hp')
                ->withValue($headerPayload)
                ->withExpires(time() + 3600)
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(false)
                ->withSameSite('strict')
        );

        $response->headers->setCookie(
            Cookie::create('jwt_s')
                ->withValue($signature)
                ->withExpires(time() + 3600)
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('strict')
        );

        // Delete old refresh token (associated with old username) and create new one
        $oldRefreshTokenString = $request->cookies->get('refresh_token');
        if ($oldRefreshTokenString) {
            $oldRefreshToken = $this->refreshTokenManager->get($oldRefreshTokenString);
            if ($oldRefreshToken) {
                $this->refreshTokenManager->delete($oldRefreshToken);
            }
        }

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        $response->headers->setCookie(
            Cookie::create('refresh_token')
                ->withValue($refreshToken->getRefreshToken())
                ->withExpires(time() + $this->refreshTokenTtl)
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('lax')
        );
    }
}

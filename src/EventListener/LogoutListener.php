<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class LogoutListener
{
    public function __construct(
        private readonly TokenExtractorInterface $tokenExtractor,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly BlockedTokenManagerInterface $blockedTokenManager,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $token = $this->tokenExtractor->extract($request);

        if (!$token) {
            return;
        }

        try {
            $payload = $this->jwtManager->parse($token);
            $this->blockedTokenManager->add($payload);
        } catch (\Exception) {
            // Token parsing failed or missing required claims, nothing to blocklist
        }
    }
}

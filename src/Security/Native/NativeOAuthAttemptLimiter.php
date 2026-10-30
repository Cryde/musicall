<?php

declare(strict_types=1);

namespace App\Security\Native;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * The per IP budget of the native social sign-ins (#1133, #1269), one bucket for every provider.
 * Every attempt is charged, refused ones included: each costs a round trip to the provider.
 */
readonly class NativeOAuthAttemptLimiter
{
    public function __construct(
        #[Target('native_oauth')]
        private RateLimiterFactoryInterface $nativeOAuthLimiter,
    ) {
    }

    /** @throws TooManyLoginAttemptsAuthenticationException */
    public function consume(Request $request): void
    {
        $limit = $this->nativeOAuthLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            // In minutes, so the message reads as the password path's does.
            $wait = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyLoginAttemptsAuthenticationException(max(1, (int) ceil($wait / 60)));
        }
    }
}

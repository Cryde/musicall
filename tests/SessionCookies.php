<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Describes the session cookies a response sets, so the paths that sign someone in can be held to
 * one expected array (#1027). An expiry is named after the configured lifetime it matches, with a
 * few seconds of slack since the cookies read time() as they are built.
 */
final class SessionCookies
{
    private const array NAMES = ['jwt_hp', 'jwt_s', 'refresh_token'];
    private const int TOLERANCE_SECONDS = 5;

    /**
     * @param iterable<Cookie> $cookies
     *
     * @return array<string, array<string, mixed>> keyed by cookie name, sorted
     */
    public static function describe(iterable $cookies, ContainerInterface $container): array
    {
        $lifetimes = [
            'token_ttl' => (int) $container->getParameter('lexik_jwt_authentication.token_ttl'),
            'refresh_ttl' => (int) $container->getParameter('gesdinet_jwt_refresh_token.ttl'),
        ];

        $described = [];
        foreach ($cookies as $cookie) {
            if (!in_array($cookie->getName(), self::NAMES, true)) {
                continue;
            }
            $described[$cookie->getName()] = [
                'expires' => self::expiry($cookie->getExpiresTime(), $lifetimes),
                'path' => $cookie->getPath(),
                'domain' => $cookie->getDomain(),
                'secure' => $cookie->isSecure(),
                'httpOnly' => $cookie->isHttpOnly(),
                'sameSite' => $cookie->getSameSite(),
                'partitioned' => $cookie->isPartitioned(),
            ];
        }
        ksort($described);

        return $described;
    }

    /** @param array<string, int> $lifetimes */
    private static function expiry(int $expiresAt, array $lifetimes): string
    {
        if ($expiresAt === 0) {
            return 'session';
        }
        $secondsLeft = $expiresAt - time();
        foreach ($lifetimes as $label => $lifetime) {
            if (abs($secondsLeft - $lifetime) <= self::TOLERANCE_SECONDS) {
                return 'now + ' . $label;
            }
        }

        return 'now + ' . $secondsLeft . 's';
    }
}

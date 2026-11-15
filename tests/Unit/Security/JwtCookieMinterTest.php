<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\JwtCookieMinter;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class JwtCookieMinterTest extends TestCase
{
    private const string JWT = 'eyJhbGciOiJSUzI1NiJ9.eyJpZCI6IjAxOSJ9.c2lnbmF0dXJl';

    /** The cookies follow whatever the providers were configured with, the lifetime included: nothing is restated. */
    public function test_it_sets_the_split_pair_from_the_configured_providers(): void
    {
        $minter = new JwtCookieMinter(
            new JWTCookieProvider('jwt_hp', 900, Cookie::SAMESITE_STRICT, '/', null, true, false, ['header', 'payload']),
            new JWTCookieProvider('jwt_s', 0, Cookie::SAMESITE_STRICT, '/', null, true, true, ['signature']),
        );
        $response = new Response();

        $minter->attachTo($response, self::JWT);

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = [
                'value' => $cookie->getValue(),
                'expiresIn' => $cookie->getExpiresTime() === 0 ? 'session' : $cookie->getExpiresTime() - time(),
                'path' => $cookie->getPath(),
                'secure' => $cookie->isSecure(),
                'httpOnly' => $cookie->isHttpOnly(),
                'sameSite' => $cookie->getSameSite(),
            ];
        }
        // time() may tick between minting and reading
        $this->assertContains($cookies['jwt_hp']['expiresIn'], [899, 900]);
        $cookies['jwt_hp']['expiresIn'] = 900;
        $this->assertSame([
            'jwt_hp' => [
                'value' => 'eyJhbGciOiJSUzI1NiJ9.eyJpZCI6IjAxOSJ9',
                'expiresIn' => 900,
                'path' => '/',
                'secure' => true,
                'httpOnly' => false,
                'sameSite' => 'strict',
            ],
            'jwt_s' => [
                'value' => 'c2lnbmF0dXJl',
                'expiresIn' => 'session',
                'path' => '/',
                'secure' => true,
                'httpOnly' => true,
                'sameSite' => 'strict',
            ],
        ], $cookies);
    }
}

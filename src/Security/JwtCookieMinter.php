<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the jwt_hp / jwt_s pair on a response that mints a JWT outside Lexik's success handler.
 * It goes through the providers that handler uses, so every path hands out the cookies
 * lexik_jwt_authentication.yaml describes, token_ttl included (#1027).
 */
readonly class JwtCookieMinter
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.cookie_provider.jwt_hp')]
        private JWTCookieProvider $headerPayloadCookie,
        #[Autowire(service: 'lexik_jwt_authentication.cookie_provider.jwt_s')]
        private JWTCookieProvider $signatureCookie,
    ) {
    }

    public function attachTo(Response $response, string $jwt): void
    {
        $response->headers->setCookie($this->headerPayloadCookie->createCookie($jwt));
        $response->headers->setCookie($this->signatureCookie->createCookie($jwt));
    }
}

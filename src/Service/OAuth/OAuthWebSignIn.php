<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Entity\User;
use App\Http\ReturnUrl;
use App\Mercure\MercureSubscriberCookie;
use App\Security\SuspensionChecker;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * How a social sign-in on the site ends, whichever provider it went through (Google, Apple #1271):
 * a redirect back to the front end, carrying the session cookies or an error key the login page maps.
 */
readonly class OAuthWebSignIn
{
    private const int JWT_COOKIE_LIFETIME = 3600;

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private MercureSubscriberCookie $mercureSubscriberCookie,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $frontendUrl,
        #[Autowire(param: 'gesdinet_jwt_refresh_token.ttl')]
        private int $refreshTokenTtl,
    ) {
    }

    public function isSafeReturnUrl(string $url): bool
    {
        return ReturnUrl::isSafe($url, $this->frontendUrl);
    }

    /** Where to land after signing in: the page the visitor came from when it is safe, the home page otherwise. */
    public function redirectUrlFor(?string $returnUrl): string
    {
        if ($returnUrl === null || $returnUrl === '' || !$this->isSafeReturnUrl($returnUrl)) {
            return $this->frontendUrl;
        }

        return str_starts_with($returnUrl, '/') ? $this->frontendUrl . $returnUrl : $returnUrl;
    }

    /**
     * Signs the user in. The cookies are minted here by hand, so no user checker runs: a suspended
     * account is refused explicitly instead.
     */
    public function complete(User $user, string $redirectUrl, Request $request): RedirectResponse
    {
        if ($user->isSuspended()) {
            return $this->fail(SuspensionChecker::MESSAGE_KEY);
        }

        $jwt = $this->jwtManager->create($user);

        // Split JWT into header.payload and signature for security
        $parts = explode('.', $jwt);
        $headerPayload = $parts[0] . '.' . $parts[1];
        $signature = $parts[2];

        $response = new RedirectResponse($redirectUrl);

        // Set cookies matching the lexik_jwt configuration
        $response->headers->setCookie(
            Cookie::create('jwt_hp')
                ->withValue($headerPayload)
                ->withExpires(time() + self::JWT_COOKIE_LIFETIME)
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(false)
                ->withSameSite('strict')
        );

        $response->headers->setCookie(
            Cookie::create('jwt_s')
                ->withValue($signature)
                ->withExpires(time() + self::JWT_COOKIE_LIFETIME)
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('strict')
        );

        // Create and set refresh token
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

        // This path mints its own cookies and never reaches Lexik's success handler, so the listener
        // that renews the Mercure subscriber cookie on login and on refresh does not fire here.
        // Without this call a social sign-in would have no subscriber token until the first refresh,
        // an hour later.
        $this->mercureSubscriberCookie->attachTo($response, $user, $request);

        return $response;
    }

    public function fail(string $error): RedirectResponse
    {
        return new RedirectResponse($this->frontendUrl . '/login?oauth_error=' . $error);
    }
}

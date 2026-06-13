<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\User;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Service\OAuth\OAuthUserData;
use App\Service\OAuth\OAuthUserService;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractOAuthController extends AbstractController
{
    private const string RETURN_URL_SESSION_PREFIX = 'oauth_return_url.';

    public function __construct(
        protected readonly ClientRegistry $clientRegistry,
        protected readonly OAuthUserService $oAuthUserService,
        protected readonly JWTTokenManagerInterface $jwtManager,
        protected readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        protected readonly RefreshTokenManagerInterface $refreshTokenManager,
        protected readonly LoggerInterface $logger,
        protected readonly string $frontendUrl,
        protected readonly int $refreshTokenTtl,
    ) {
    }

    abstract protected function getProviderName(): string;

    abstract protected function getClientName(): string;

    /**
     * @return string[]
     */
    abstract protected function getScopes(): array;

    abstract protected function extractUserData(object $resourceOwner): OAuthUserData;

    public function connect(Request $request): RedirectResponse
    {
        $returnUrl = $request->query->getString('return_url');
        $options = [];

        if ($returnUrl !== '' && $this->isValidReturnUrl($returnUrl)) {
            // Use a random, unguessable state nonce (KnpU stores it in the session
            // and verifies it on callback for CSRF) and stash the return URL against
            // it, rather than encoding the return URL into the state itself - which
            // made the state predictable (SECURITY-FIX.md finding 13).
            $nonce = bin2hex(random_bytes(32));
            $request->getSession()->set(self::RETURN_URL_SESSION_PREFIX . $nonce, $returnUrl);
            $options['state'] = $nonce;
        }

        return $this->clientRegistry
            ->getClient($this->getClientName())
            ->redirect($this->getScopes(), $options);
    }

    private function isValidReturnUrl(string $url): bool
    {
        // Only allow relative URLs or URLs from the same domain
        if (str_starts_with($url, '/')) {
            return true;
        }

        $parsedUrl = parse_url($url);
        $frontendParsed = parse_url($this->frontendUrl);

        return isset($parsedUrl['host'], $frontendParsed['host']) && $parsedUrl['host'] === $frontendParsed['host'];
    }

    public function callback(Request $request): RedirectResponse
    {
        $client = $this->clientRegistry->getClient($this->getClientName());

        try {
            $accessToken = $client->getAccessToken();
            $resourceOwner = $client->fetchUserFromToken($accessToken);

            $userData = $this->extractUserData($resourceOwner);

            /** @var User|null $currentUser */
            $currentUser = $this->getUser();

            $result = $this->oAuthUserService->findOrCreateUser(
                $userData,
                $this->getProviderName(),
                $currentUser
            );

            return $this->createAuthenticatedRedirect($result->user, $request);
        } catch (OAuthEmailExistsException) {
            return $this->redirectWithError('email_exists');
        } catch (\Exception $e) {
            $this->logger->error('OAuth authentication failed', [
                'provider' => $this->getProviderName(),
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError('oauth_failed');
        }
    }

    protected function createAuthenticatedRedirect(User $user, Request $request): RedirectResponse
    {
        $jwt = $this->jwtManager->create($user);

        // Split JWT into header.payload and signature for security
        $parts = explode('.', $jwt);
        $headerPayload = $parts[0] . '.' . $parts[1];
        $signature = $parts[2];

        // Extract return_url from state parameter
        $redirectUrl = $this->extractReturnUrlFromState($request);

        $response = new RedirectResponse($redirectUrl);

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

        return $response;
    }

    private function extractReturnUrlFromState(Request $request): string
    {
        $state = $request->query->getString('state');
        if ($state === '') {
            return $this->frontendUrl;
        }

        // The state is now an opaque nonce; the return URL was stashed against it in
        // the session at connect() time. Resolve and consume it (single use).
        $session = $request->getSession();
        $sessionKey = self::RETURN_URL_SESSION_PREFIX . $state;
        $returnUrl = $session->get($sessionKey);
        $session->remove($sessionKey);

        if (is_string($returnUrl) && $returnUrl !== '' && $this->isValidReturnUrl($returnUrl)) {
            // Ensure it's a full URL
            if (str_starts_with($returnUrl, '/')) {
                return $this->frontendUrl . $returnUrl;
            }
            return $returnUrl;
        }

        return $this->frontendUrl;
    }

    protected function redirectWithError(string $error): RedirectResponse
    {
        return new RedirectResponse($this->frontendUrl . '/login?oauth_error=' . $error);
    }
}

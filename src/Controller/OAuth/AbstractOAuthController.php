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
            $options['state'] = base64_encode(json_encode(['return_url' => $returnUrl], JSON_THROW_ON_ERROR));
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

        try {
            $decoded = base64_decode($state, true);
            if ($decoded === false) {
                return $this->frontendUrl;
            }

            /** @var array{return_url?: string} $stateData */
            $stateData = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            $returnUrl = $stateData['return_url'] ?? null;

            if ($returnUrl !== null && $this->isValidReturnUrl($returnUrl)) {
                // Ensure it's a full URL
                if (str_starts_with($returnUrl, '/')) {
                    return $this->frontendUrl . $returnUrl;
                }
                return $returnUrl;
            }
        } catch (\JsonException) {
            // Invalid state, use default redirect
        }

        return $this->frontendUrl;
    }

    protected function redirectWithError(string $error): RedirectResponse
    {
        return new RedirectResponse($this->frontendUrl . '/login?oauth_error=' . $error);
    }
}

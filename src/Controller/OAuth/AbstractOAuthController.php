<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\User;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Exception\OAuth\OAuthEmailNotVerifiedException;
use App\Service\OAuth\OAuthUserData;
use App\Service\OAuth\OAuthUserService;
use App\Service\OAuth\OAuthWebSignIn;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractOAuthController extends AbstractController
{
    private const string RETURN_URL_SESSION_PREFIX = 'oauth_return_url.';

    public function __construct(
        protected readonly ClientRegistry $clientRegistry,
        protected readonly OAuthUserService $oAuthUserService,
        protected readonly OAuthWebSignIn $webSignIn,
        protected readonly LoggerInterface $logger,
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

        if ($returnUrl !== '' && $this->webSignIn->isSafeReturnUrl($returnUrl)) {
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

            $redirectUrl = $this->webSignIn->redirectUrlFor($this->extractReturnUrlFromState($request));

            return $this->webSignIn->complete($result->user, $redirectUrl, $request);
        } catch (OAuthEmailExistsException) {
            return $this->webSignIn->fail('email_exists');
        } catch (OAuthEmailNotVerifiedException) {
            return $this->webSignIn->fail('email_not_verified');
        } catch (\Exception $e) {
            $this->logger->error('OAuth authentication failed', [
                'provider' => $this->getProviderName(),
                'error' => $e->getMessage(),
            ]);

            return $this->webSignIn->fail('oauth_failed');
        }
    }

    private function extractReturnUrlFromState(Request $request): ?string
    {
        $state = $request->query->getString('state');
        if ($state === '') {
            return null;
        }

        // The state is now an opaque nonce; the return URL was stashed against it in
        // the session at connect() time. Resolve and consume it (single use).
        $session = $request->getSession();
        $sessionKey = self::RETURN_URL_SESSION_PREFIX . $state;
        $returnUrl = $session->get($sessionKey);
        $session->remove($sessionKey);

        return is_string($returnUrl) ? $returnUrl : null;
    }
}

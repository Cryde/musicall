<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\User;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Exception\OAuth\OAuthEmailNotVerifiedException;
use App\Locale\RequestLocaleResolver;
use App\Service\OAuth\OAuthUserData;
use App\Service\OAuth\OAuthUserService;
use App\Service\OAuth\OAuthWebSignIn;
use App\Service\User\RegistrationCampaign;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractOAuthController extends AbstractController
{
    private const string RETURN_URL_SESSION_PREFIX = 'oauth_return_url.';
    private const string CAMPAIGN_SESSION_PREFIX = 'oauth_campaign.';
    private const string LOCALE_SESSION_PREFIX = 'oauth_locale.';

    public function __construct(
        protected readonly ClientRegistry $clientRegistry,
        protected readonly OAuthUserService $oAuthUserService,
        protected readonly OAuthWebSignIn $webSignIn,
        protected readonly LoggerInterface $logger,
        private readonly RequestLocaleResolver $requestLocaleResolver,
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
        // Use a random, unguessable state nonce (KnpU stores it in the session
        // and verifies it on callback for CSRF) and stash the return URL against
        // it, rather than encoding the return URL into the state itself - which
        // made the state predictable (SECURITY-FIX.md finding 13).
        $nonce = bin2hex(random_bytes(32));
        $session = $request->getSession();

        $returnUrl = $request->query->getString('return_url');
        if ($returnUrl !== '' && $this->webSignIn->isSafeReturnUrl($returnUrl)) {
            $session->set(self::RETURN_URL_SESSION_PREFIX . $nonce, $returnUrl);
        }
        // Server side like the return URL, never in the browser (#1369); used only if the sign-in creates the account.
        $campaign = RegistrationCampaign::fromFields($request->query->all());
        if (!$campaign->isEmpty()) {
            $session->set(self::CAMPAIGN_SESSION_PREFIX . $nonce, $campaign->toFields());
        }
        // The page's language (#1371): the callback is a redirect from Google, with no X-Locale and no `/en` prefix.
        $pageLocale = $this->requestLocaleResolver->enabledOrNull($request->query->all()['locale'] ?? null);
        if ($pageLocale !== null) {
            $session->set(self::LOCALE_SESSION_PREFIX . $nonce, $pageLocale);
        }

        return $this->clientRegistry
            ->getClient($this->getClientName())
            ->redirect($this->getScopes(), ['state' => $nonce]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $client = $this->clientRegistry->getClient($this->getClientName());
        // Read before anything can fail, so a refused sign-in still knows the way back.
        $stashedReturnUrl = $this->takeFromSession($request, self::RETURN_URL_SESSION_PREFIX);
        $returnUrl = is_string($stashedReturnUrl) ? $stashedReturnUrl : null;
        $stashedCampaign = $this->takeFromSession($request, self::CAMPAIGN_SESSION_PREFIX);
        $campaign = RegistrationCampaign::fromFields(is_array($stashedCampaign) ? $stashedCampaign : []);
        $pageLocale = $this->requestLocaleResolver->enabledOrNull($this->takeFromSession($request, self::LOCALE_SESSION_PREFIX));

        try {
            $accessToken = $client->getAccessToken();
            $resourceOwner = $client->fetchUserFromToken($accessToken);

            $userData = $this->extractUserData($resourceOwner);

            /** @var User|null $currentUser */
            $currentUser = $this->getUser();

            $result = $this->oAuthUserService->findOrCreateUser(
                $userData,
                $this->getProviderName(),
                $currentUser,
                campaign: $campaign,
                pageLocale: $pageLocale,
            );

            return $this->webSignIn->complete($result->user, $this->webSignIn->redirectUrlFor($returnUrl), $request);
        } catch (OAuthEmailExistsException) {
            return $this->webSignIn->fail('email_exists', $returnUrl);
        } catch (OAuthEmailNotVerifiedException) {
            return $this->webSignIn->fail('email_not_verified', $returnUrl);
        } catch (\Exception $e) {
            $this->logger->error('OAuth authentication failed', [
                'provider' => $this->getProviderName(),
                'error' => $e->getMessage(),
            ]);

            return $this->webSignIn->fail('oauth_failed', $returnUrl);
        }
    }

    private function takeFromSession(Request $request, string $prefix): mixed
    {
        $state = $request->query->getString('state');
        if ($state === '') {
            return null;
        }

        // The state is an opaque nonce; what connect() stashed against it in the
        // session is resolved and consumed (single use).
        $session = $request->getSession();
        $value = $session->get($prefix . $state);
        $session->remove($prefix . $state);

        return $value;
    }
}

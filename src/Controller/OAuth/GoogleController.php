<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Service\OAuth\OAuthUserData;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractOAuthController
{
    protected function getProviderName(): string
    {
        return 'google';
    }

    protected function getClientName(): string
    {
        return 'google';
    }

    protected function getScopes(): array
    {
        return ['email', 'profile'];
    }

    protected function extractUserData(object $resourceOwner): OAuthUserData
    {
        /** @var GoogleUser $resourceOwner */
        if (!$email = $resourceOwner->getEmail()) {
            throw new \LogicException('No email address');
        }
        return new OAuthUserData(
            id: $resourceOwner->getId(),
            email: $email,
            username: $resourceOwner->getName() ?: $email,
            pictureUrl: $this->getHighResolutionPictureUrl($resourceOwner->getAvatar()),
        );
    }

    private function getHighResolutionPictureUrl(?string $pictureUrl): ?string
    {
        if ($pictureUrl === null) {
            return null;
        }

        return preg_replace('/=s\d+-c$/', '=s500-c', $pictureUrl);
    }

    #[Route('/oauth/google', name: 'oauth_google_start')]
    public function start(): RedirectResponse
    {
        return $this->connect();
    }

    #[Route('/oauth/google/callback', name: 'oauth_google_callback')]
    public function callbackAction(Request $request): RedirectResponse
    {
        return $this->callback($request);
    }
}

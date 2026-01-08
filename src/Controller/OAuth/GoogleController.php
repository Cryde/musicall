<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

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

    protected function extractUserData(object $resourceOwner): array
    {
        /** @var GoogleUser $resourceOwner */
        return [
            'id' => $resourceOwner->getId(),
            'email' => $resourceOwner->getEmail(),
            'username' => $resourceOwner->getName() ?: $resourceOwner->getEmail(),
        ];
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

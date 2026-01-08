<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use League\OAuth2\Client\Provider\FacebookUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class FacebookController extends AbstractOAuthController
{
    protected function getProviderName(): string
    {
        return 'facebook';
    }

    protected function getClientName(): string
    {
        return 'facebook';
    }

    protected function getScopes(): array
    {
        return ['email', 'public_profile'];
    }

    protected function extractUserData(object $resourceOwner): array
    {
        /** @var FacebookUser $resourceOwner */
        return [
            'id' => $resourceOwner->getId(),
            'email' => $resourceOwner->getEmail(),
            'username' => $resourceOwner->getName() ?? $resourceOwner->getEmail(),
        ];
    }

    #[Route('/oauth/facebook', name: 'oauth_facebook_start')]
    public function start(): RedirectResponse
    {
        return $this->connect();
    }

    #[Route('/oauth/facebook/callback', name: 'oauth_facebook_callback')]
    public function callbackAction(Request $request): RedirectResponse
    {
        return $this->callback($request);
    }

    /**
     * Facebook Data Deletion Callback
     * Called by Facebook when a user requests deletion of their data.
     * @see https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
     */
    #[Route('/oauth/facebook/deletion', name: 'oauth_facebook_deletion', methods: ['POST'])]
    public function deletionCallback(Request $request): JsonResponse
    {
        // TODO: Parse signed_request, verify signature, and process actual deletion
        // For now, we just acknowledge the request with a confirmation code
        $confirmationCode = Uuid::v4()->toRfc4122();

        $statusUrl = $this->generateUrl(
            'oauth_facebook_deletion_status',
            ['code' => $confirmationCode],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse([
            'url' => $statusUrl,
            'confirmation_code' => $confirmationCode,
        ]);
    }

    #[Route('/oauth/facebook/deletion-status/{code}', name: 'oauth_facebook_deletion_status', methods: ['GET'])]
    public function deletionStatus(string $code): JsonResponse
    {
        // TODO: Look up actual deletion status from database
        // For now, always return pending status
        return new JsonResponse([
            'status' => 'pending',
            'confirmation_code' => $code,
            'message' => 'Votre demande de suppression a été reçue et sera traitée prochainement.',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

use App\Entity\SocialAccount;
use App\Repository\SocialAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Turns the authorization code of a sign-in into the refresh token Apple wants revoked when the
 * account is deleted (#1269), and keeps it on the Apple social account.
 *
 * Best effort: Apple failing here must not cost anyone their sign-in, and the app sends a fresh code
 * with every sign-in, so the next one tries again.
 */
readonly class AppleRefreshTokenRecorder
{
    public function __construct(
        private AppleTokenClientInterface $tokenClient,
        private SocialAccountRepository $socialAccountRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function record(string $subject, string $authorizationCode, string $clientId): void
    {
        try {
            $tokens = $this->tokenClient->exchangeAuthorizationCode($authorizationCode, $clientId);
        } catch (AppleTokenRequestException $exception) {
            $this->logger->warning('Could not exchange an Apple authorization code', ['error' => $exception->getMessage()]);

            return;
        }

        // A code from another Apple ID would put a token on this account that revokes someone else's.
        if ($tokens->subject !== $subject) {
            $this->logger->warning('An Apple authorization code belonged to another Apple ID than its identity token');

            return;
        }

        $socialAccount = $this->socialAccountRepository->findByProviderAndProviderId(SocialAccount::PROVIDER_APPLE, $subject);
        if (!$socialAccount instanceof SocialAccount) {
            return;
        }
        $socialAccount->refreshToken = $tokens->refreshToken;
        $socialAccount->refreshTokenClientId = $clientId;
        $this->entityManager->flush();
    }
}

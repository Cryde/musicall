<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

use App\Entity\SocialAccount;
use App\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * Apple requires an app to revoke a person's tokens when they delete their account (#1270), which
 * also takes the app off the « Apps using Apple ID » list of their Apple account.
 *
 * In two steps, because deleting the account removes the social accounts that hold the tokens: read
 * them first, revoke them once the deletion is committed.
 */
readonly class AppleTokenRevoker
{
    public function __construct(
        private AppleTokenClientInterface $tokenClient,
        private LoggerInterface $logger,
    ) {
    }

    /** @return list<AppleRefreshToken> */
    public function refreshTokensOf(User $user): array
    {
        $refreshTokens = [];
        foreach ($user->socialAccounts as $socialAccount) {
            if ($socialAccount->provider === SocialAccount::PROVIDER_APPLE
                && $socialAccount->refreshToken !== null
                && $socialAccount->refreshTokenClientId !== null
            ) {
                $refreshTokens[] = new AppleRefreshToken($socialAccount->refreshToken, $socialAccount->refreshTokenClientId);
            }
        }

        return $refreshTokens;
    }

    /**
     * Best effort: the deletion is already committed when this runs, and Apple being down must not
     * turn it into an error. Anything thrown is logged, with the user id since the social account that
     * held the token is gone.
     *
     * @param list<AppleRefreshToken> $refreshTokens
     */
    public function revoke(array $refreshTokens, string $userId): void
    {
        foreach ($refreshTokens as $refreshToken) {
            try {
                $this->tokenClient->revoke($refreshToken->token, $refreshToken->clientId);
            } catch (\Throwable $exception) {
                $this->logger->error('Could not revoke an Apple refresh token of a deleted account', [
                    'exception' => $exception,
                    'user_id' => $userId,
                    'client_id' => $refreshToken->clientId,
                ]);
            }
        }
    }
}

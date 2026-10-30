<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

interface AppleTokenClientInterface
{
    /**
     * @throws AppleTokenRequestException Apple refused the code, could not be reached, or our credentials are not configured
     */
    public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): AppleTokens;

    /**
     * Revokes a refresh token, under the client id it was issued to: Apple refuses any other.
     *
     * @throws AppleTokenRequestException Apple refused the revocation, could not be reached, or our credentials are not configured
     */
    public function revoke(string $refreshToken, string $clientId): void;
}

<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

use Firebase\JWT\JWT;

/**
 * The `client_secret` Apple's token endpoints want: a short-lived JWT signed ES256 with our .p8 key
 * (#1269). The key is read only here, so a server without the Apple credentials keeps every other
 * sign-in working and only fails the Apple calls that need it.
 */
readonly class AppleClientSecret
{
    private const int LIFETIME = 300; // seconds, one request's worth; Apple allows up to six months

    public function __construct(
        private string $teamId,
        private string $keyId,
        private string $privateKeyPath,
    ) {
    }

    /** @throws AppleTokenRequestException */
    public function for(string $clientId): string
    {
        if ($this->teamId === '' || $this->keyId === '' || $clientId === '') {
            throw new AppleTokenRequestException('The Apple team id, key id or client id is not set');
        }
        if ($this->privateKeyPath === '' || !is_readable($this->privateKeyPath)) {
            throw new AppleTokenRequestException('The Apple private key is not readable');
        }

        $issuedAt = time();
        try {
            return JWT::encode([
                'iss' => $this->teamId,
                'iat' => $issuedAt,
                'exp' => $issuedAt + self::LIFETIME,
                'aud' => AppleIdentityTokenVerifier::ISSUER,
                'sub' => $clientId,
            ], (string) file_get_contents($this->privateKeyPath), 'ES256', $this->keyId);
        } catch (\DomainException $exception) {
            throw new AppleTokenRequestException('The Apple private key cannot sign', 0, $exception);
        }
    }
}

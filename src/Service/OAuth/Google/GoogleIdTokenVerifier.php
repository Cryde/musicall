<?php

declare(strict_types=1);

namespace App\Service\OAuth\Google;

use Google\Client;

/**
 * Google\Client::verifyIdToken() checks the signature, the expiry, the issuer and that the audience is
 * this site's client id, and answers false for those. It throws for the rest, and the two kinds have
 * to be told apart: a malformed token is the caller's fault, unreachable certificates are not (#1133).
 */
readonly class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public function __construct(
        private Client $client,
    ) {
    }

    public function verify(string $idToken): array
    {
        // The library compares the audience only when it has one to compare with, so an unset client
        // id would accept a token Google issued to any other app. Fail closed instead.
        $clientId = $this->client->getClientId();
        if (!is_string($clientId) || $clientId === '') {
            throw new GoogleIdTokenUnverifiableException('GOOGLE_CLIENT_ID is not set');
        }

        try {
            $claims = $this->client->verifyIdToken($idToken);
        } catch (\UnexpectedValueException $exception) {
            // firebase/php-jwt uses this one class for both: a token it cannot decode (code 0), and
            // Google answering the certificate request with an HTTP error (the status as the code).
            if ($exception->getCode() !== 0) {
                throw new GoogleIdTokenUnverifiableException('Google\'s certificates are unavailable', 0, $exception);
            }

            throw new InvalidGoogleIdTokenException('Malformed Google ID token', 0, $exception);
        } catch (\Throwable $exception) {
            // A key Google does not publish, or a header the decoder chokes on: the token's fault.
            if ($exception instanceof \OutOfBoundsException || $exception instanceof \TypeError) {
                throw new InvalidGoogleIdTokenException('Unknown Google signing key', 0, $exception);
            }

            throw new GoogleIdTokenUnverifiableException('Could not verify a Google ID token', 0, $exception);
        }

        // Checked again here rather than trusted to the library, which lets a token without `aud` through.
        if (!is_array($claims) || ($claims['aud'] ?? null) !== $clientId) {
            throw new InvalidGoogleIdTokenException('Google ID token refused');
        }

        return $claims;
    }
}

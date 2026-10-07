<?php

declare(strict_types=1);

namespace App\Service\OAuth\Google;

interface GoogleIdTokenVerifierInterface
{
    /**
     * The claims of an ID token Google issued for this site's client.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidGoogleIdTokenException      the token is malformed, expired, forged or for another client
     * @throws GoogleIdTokenUnverifiableException Google's certificates could not be fetched
     */
    public function verify(string $idToken): array;
}

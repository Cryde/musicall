<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

interface AppleIdentityTokenVerifierInterface
{
    /**
     * The claims of an identity token Apple issued for the given client id (#1269).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidAppleIdentityTokenException      the token is malformed, expired, forged or for another client
     * @throws AppleIdentityTokenUnverifiableException Apple's keys could not be fetched, or no client id is configured
     */
    public function verify(string $identityToken, string $audience): array;
}

<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

/** A stored Apple refresh token and the client id it was issued to, which is the one Apple revokes it under. */
readonly class AppleRefreshToken
{
    public function __construct(
        public string $token,
        public string $clientId,
    ) {
    }
}

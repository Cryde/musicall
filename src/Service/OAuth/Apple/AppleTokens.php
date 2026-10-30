<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

/** What Apple answers to an authorization code: the refresh token to revoke one day, and whose it is. */
readonly class AppleTokens
{
    public function __construct(
        public string $refreshToken,
        public string $subject,
    ) {
    }
}

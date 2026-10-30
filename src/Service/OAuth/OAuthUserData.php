<?php

declare(strict_types=1);

namespace App\Service\OAuth;

readonly class OAuthUserData
{
    public function __construct(
        public string $id,
        public string $email,
        public string $username,
        public ?string $pictureUrl,
        public bool $emailVerified,
        // A real name for the profile, when the provider gives one apart from the username (Apple, #1269).
        public ?string $displayName = null,
    ) {
    }
}

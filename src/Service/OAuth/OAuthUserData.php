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
    ) {
    }
}

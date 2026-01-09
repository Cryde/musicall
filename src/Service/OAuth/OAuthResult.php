<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Entity\User;

readonly class OAuthResult
{
    public function __construct(
        public User $user,
        public bool $isNew,
    ) {
    }
}

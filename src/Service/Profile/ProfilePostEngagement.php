<?php

declare(strict_types=1);

namespace App\Service\Profile;

/** What a wall post shows under it for one viewer (#1316). */
final readonly class ProfilePostEngagement
{
    public function __construct(
        public int $likeCount = 0,
        public bool $hasLiked = false,
        public int $commentCount = 0,
    ) {
    }
}

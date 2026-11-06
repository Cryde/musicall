<?php declare(strict_types=1);

namespace App\Security\Profile;

use App\Entity\User;
use App\Exception\User\PrivateProfileException;

/**
 * « Profil privé » hides all of a member's public pages: the profile, the musician page, the teacher page.
 */
readonly class PublicProfileGuard
{
    public function assertPublic(User $user): void
    {
        if (!$user->profile->isPublic) {
            throw new PrivateProfileException();
        }
    }
}

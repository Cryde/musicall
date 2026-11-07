<?php declare(strict_types=1);

namespace App\Security\Profile;

use App\Entity\User;
use App\Exception\User\PrivateProfileException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * « Profil privé » hides all of a member's public pages, the profile, the musician page and the teacher page, from
 * everyone but the member, who edits them there (#1317).
 */
readonly class PublicProfileGuard
{
    public function __construct(private Security $security)
    {
    }

    public function assertVisible(User $member): void
    {
        if ($member->profile->isPublic) {
            return;
        }
        $viewer = $this->security->getUser();
        if (!$viewer instanceof User || $viewer->id !== $member->id) {
            throw new PrivateProfileException();
        }
    }
}

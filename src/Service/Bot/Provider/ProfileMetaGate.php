<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\User;

/**
 * Whether a member's page may be described at all: nothing for an account that is gone, a bare card for a
 * private profile, so neither a share card nor a search engine ever shows what the member hid.
 */
readonly class ProfileMetaGate
{
    /**
     * @return array{title?: string, description?: string}|null the meta to use instead, or null when the page can be described
     */
    public function withheld(User $user): ?array
    {
        if (!$user->isPubliclyVisible()) {
            return [];
        }

        if (!$user->profile->isPublic) {
            return [
                'title' => 'Profil privé - MusicAll',
                'description' => 'Ce profil est privé.',
            ];
        }

        return null;
    }
}

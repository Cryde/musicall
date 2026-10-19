<?php

declare(strict_types=1);

namespace App\Translation;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The naming rules of User and BandSpaceMembership with a closed account named in the reader's locale.
 * Entities cannot reach the translator, so they take the label and this is where it comes from.
 */
readonly class DisplayNameTranslator
{
    private const string DELETED_USER_KEY = 'user.deleted_display_name';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /** In the request's locale. */
    public function deletedUser(): string
    {
        return $this->translator->trans(self::DELETED_USER_KEY);
    }

    public function publicName(User $user): string
    {
        return $user->publicName($this->deletedUser());
    }

    public function memberName(BandSpaceMembership $membership): string
    {
        return $membership->displayName($this->deletedUser());
    }
}

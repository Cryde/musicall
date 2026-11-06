<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

class ProfilePostCommentAuthor
{
    public string $username;

    public string $displayName;

    public ?string $profilePictureUrl = null;

    /** Set when the account is closed: the comment stays, signed « Utilisateur supprimé ». */
    public ?\DateTimeInterface $deletionDatetime = null;
}

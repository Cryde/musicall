<?php declare(strict_types=1);

namespace App\ApiResource\Musician\Announce;

class Author
{
    public string $id;

    public string $username;

    /** The name the site shows, next to the username mentions and links use (#1118). */
    public string $displayName;

    public ?\DateTimeImmutable $deletionDatetime = null;

    public ?string $profilePictureUrl = null;

    public bool $hasMusicianProfile = false;
}

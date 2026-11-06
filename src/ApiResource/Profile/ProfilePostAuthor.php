<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

class ProfilePostAuthor
{
    public string $username;

    public string $displayName;

    public ?string $profilePictureUrl = null;
}

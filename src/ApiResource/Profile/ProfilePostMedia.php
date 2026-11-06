<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

class ProfilePostMedia
{
    public string $platform;

    public string $embedId;

    public string $url;

    public ?string $title = null;
}

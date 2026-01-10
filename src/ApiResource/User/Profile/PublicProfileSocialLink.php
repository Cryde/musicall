<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

class PublicProfileSocialLink
{
    public string $platform;

    public string $platformLabel;

    public string $url;
}

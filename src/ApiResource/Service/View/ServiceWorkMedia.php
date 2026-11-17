<?php

declare(strict_types=1);

namespace App\ApiResource\Service\View;

/** The YouTube, SoundCloud or Spotify embed of a work (#1407). */
class ServiceWorkMedia
{
    public string $platform;

    public string $url;

    public string $embedId;

    public ?string $title = null;
}

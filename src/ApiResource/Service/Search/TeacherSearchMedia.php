<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

class TeacherSearchMedia
{
    public string $platform;
    /** What the platform's player is given, see mediaEmbed.js. */
    public string $embedId;
    public ?string $title = null;
}

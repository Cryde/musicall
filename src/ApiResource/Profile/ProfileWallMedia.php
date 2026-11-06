<?php

declare(strict_types=1);

namespace App\ApiResource\Profile;

/** A media added to a musician or teacher profile, as a wall line plays it. */
class ProfileWallMedia
{
    public const string PROFILE_MUSICIAN = 'musician';
    public const string PROFILE_TEACHER = 'teacher';

    public string $id;

    /** Which profile holds it: `musician` or `teacher`. */
    public string $profile;

    public string $platform;

    public string $platformLabel;

    public string $url;

    public string $embedId;

    public ?string $title = null;

    public ?string $thumbnailUrl = null;
}

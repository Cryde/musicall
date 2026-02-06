<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

class TeacherProfileSocialLink
{
    public ?int $id = null;

    public string $platform;

    public string $url;
}

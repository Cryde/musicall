<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Profile;

use App\Validator\Musician\SupportedMediaUrl;
use App\Validator\Teacher\TeacherMediaLimit;
use Symfony\Component\Validator\Constraints\NotBlank;

#[TeacherMediaLimit]
class Media
{
    #[NotBlank]
    #[SupportedMediaUrl]
    public string $url;

    public ?string $title = null;
}

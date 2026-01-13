<?php

declare(strict_types=1);

namespace App\ApiResource\Musician\Profile;

use App\Validator\Musician\MusicianMediaLimit;
use App\Validator\Musician\SupportedMediaUrl;
use Symfony\Component\Validator\Constraints\NotBlank;

#[MusicianMediaLimit]
class Media
{
    #[NotBlank]
    #[SupportedMediaUrl]
    public string $url;

    public ?string $title = null;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use App\Validator\Musician\SupportedMediaUrl;
use Symfony\Component\Validator\Constraints as Assert;

class MyServiceMediaInput
{
    #[Assert\NotBlank]
    #[SupportedMediaUrl]
    public ?string $url = null;

    #[Assert\Length(max: 255)]
    public ?string $title = null;
}

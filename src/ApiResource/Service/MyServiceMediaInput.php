<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use Symfony\Component\Validator\Constraints as Assert;

class MyServiceMediaInput
{
    /** Checked against the platforms of the service's kind by the processor (ServiceMediaUrlChecker). */
    #[Assert\NotBlank]
    public ?string $url = null;

    #[Assert\Length(max: 255)]
    public ?string $title = null;
}

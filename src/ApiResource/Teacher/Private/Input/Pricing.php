<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Pricing
{
    #[Assert\NotBlank(message: 'profile.teacher.pricing.duration_required')]
    #[Assert\Choice(
        choices: ['30min', '1h', '1h30', '2h'],
        message: 'profile.teacher.pricing.duration_invalid',
    )]
    public ?string $duration = null;

    #[Assert\NotNull(message: 'profile.teacher.pricing.price_required')]
    #[Assert\PositiveOrZero(message: 'profile.teacher.pricing.price_positive')]
    public ?int $price = null;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Pricing
{
    #[Assert\NotBlank(message: 'La durée est obligatoire')]
    #[Assert\Choice(
        choices: ['30min', '1h', '1h30', '2h'],
        message: 'Durée invalide : {{ value }}',
    )]
    public ?string $duration = null;

    #[Assert\NotNull(message: 'Le prix est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif')]
    public ?int $price = null;
}

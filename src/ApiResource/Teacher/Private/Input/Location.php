<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Location
{
    #[Assert\NotBlank(message: 'Le type de lieu est obligatoire')]
    #[Assert\Choice(
        choices: ['teacher_place', 'student_place', 'online'],
        message: 'Type de lieu invalide : {{ value }}',
    )]
    public ?string $type = null;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $country = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    #[Assert\PositiveOrZero(message: 'Le rayon doit être positif')]
    public ?int $radius = null;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Location
{
    #[Assert\NotBlank(message: 'profile.teacher.location.type_required')]
    #[Assert\Choice(
        choices: ['teacher_place', 'student_place', 'online'],
        message: 'profile.teacher.location.type_invalid',
    )]
    public ?string $type = null;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $country = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    #[Assert\PositiveOrZero(message: 'profile.teacher.location.radius_positive')]
    public ?int $radius = null;
}

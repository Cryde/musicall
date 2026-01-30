<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

class TeacherProfileLocation
{
    public string $type;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $country = null;

    public ?string $latitude = null;

    public ?string $longitude = null;

    public ?int $radius = null;
}

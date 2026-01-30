<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

class TeacherProfilePricing
{
    public ?string $id = null;

    public string $duration;

    public int $price;
}

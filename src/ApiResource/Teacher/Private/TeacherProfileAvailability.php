<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private;

class TeacherProfileAvailability
{
    public ?string $id = null;

    public string $dayOfWeek;

    public string $startTime;

    public string $endTime;
}

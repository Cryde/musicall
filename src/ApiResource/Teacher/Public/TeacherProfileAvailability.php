<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

class TeacherProfileAvailability
{
    public string $dayOfWeek;

    public int $dayOfWeekOrder;

    public string $startTime;

    public string $endTime;
}

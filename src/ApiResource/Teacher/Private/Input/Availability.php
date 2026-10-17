<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Availability
{
    #[Assert\NotBlank(message: 'profile.teacher.availability.day_required')]
    #[Assert\Choice(
        choices: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        message: 'profile.teacher.availability.day_invalid',
    )]
    public ?string $dayOfWeek = null;

    #[Assert\NotBlank(message: 'profile.teacher.availability.start_time_required')]
    #[Assert\Time(message: 'profile.teacher.availability.start_time_invalid', withSeconds: false)]
    public ?string $startTime = null;

    #[Assert\NotBlank(message: 'profile.teacher.availability.end_time_required')]
    #[Assert\Time(message: 'profile.teacher.availability.end_time_invalid', withSeconds: false)]
    public ?string $endTime = null;
}

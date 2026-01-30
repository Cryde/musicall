<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class Availability
{
    #[Assert\NotBlank(message: 'Le jour est obligatoire')]
    #[Assert\Choice(
        choices: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        message: 'Jour invalide : {{ value }}',
    )]
    public ?string $dayOfWeek = null;

    #[Assert\NotBlank(message: 'L\'heure de début est obligatoire')]
    #[Assert\Time(message: 'Format d\'heure de début invalide (attendu HH:MM)', withSeconds: false)]
    public ?string $startTime = null;

    #[Assert\NotBlank(message: 'L\'heure de fin est obligatoire')]
    #[Assert\Time(message: 'Format d\'heure de fin invalide (attendu HH:MM)', withSeconds: false)]
    public ?string $endTime = null;
}

<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use Symfony\Component\Validator\Constraints as Assert;

class MusicianProfileInstrumentInput
{
    #[Assert\NotBlank(message: 'L\'instrument est requis')]
    public string $instrumentId;

    #[Assert\NotBlank(message: 'Le niveau est requis')]
    #[Assert\Choice(choices: ['beginner', 'intermediate', 'advanced', 'professional'], message: 'Niveau invalide')]
    public string $skillLevel;
}

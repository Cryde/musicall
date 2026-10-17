<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use Symfony\Component\Validator\Constraints as Assert;

class MusicianProfileInstrumentInput
{
    #[Assert\NotBlank(message: 'profile.musician.instrument.instrument_required')]
    public string $instrumentId;

    #[Assert\NotBlank(message: 'profile.musician.instrument.level_required')]
    #[Assert\Choice(choices: ['beginner', 'intermediate', 'advanced', 'professional'], message: 'profile.musician.instrument.level_invalid')]
    public string $skillLevel;
}

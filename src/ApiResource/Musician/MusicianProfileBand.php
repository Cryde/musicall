<?php

declare(strict_types=1);

namespace App\ApiResource\Musician;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** A band the member plays or played in (#1313), written on the musician profile and read on the public pages. */
class MusicianProfileBand
{
    #[Assert\NotBlank(message: 'profile.musician.band.name_required', normalizer: 'trim')]
    #[Assert\Length(max: 80, maxMessage: 'profile.musician.band.name_too_long')]
    public ?string $name = null;

    public ?string $styleId = null;

    #[ApiProperty(writable: false)]
    public ?string $styleName = null;

    #[Assert\Length(max: 80, maxMessage: 'profile.musician.band.role_too_long')]
    public ?string $role = null;

    public bool $current = true;

    #[Assert\Url(message: 'profile.musician.band.url_invalid', requireTld: true)]
    #[Assert\Length(max: 500, maxMessage: 'profile.musician.band.url_too_long')]
    public ?string $url = null;
}

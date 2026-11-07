<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Musician\PublicMusicianProfileInstrument;
use App\ApiResource\Musician\PublicMusicianProfileStyle;
use App\ApiResource\Profile\ProfileWallMedia;

/** What the profile page says about the member as a musician (#1317), without counting a musician page view. */
class PublicProfileMusician
{
    public ?string $availabilityStatus = null;

    public ?string $availabilityStatusLabel = null;

    /** @var PublicMusicianProfileInstrument[] */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    /** @var PublicMusicianProfileStyle[] */
    #[ApiProperty(genId: false)]
    public array $styles = [];

    /** @var ProfileWallMedia[] in the order the member set, for « Écouter » */
    #[ApiProperty(genId: false)]
    public array $media = [];
}

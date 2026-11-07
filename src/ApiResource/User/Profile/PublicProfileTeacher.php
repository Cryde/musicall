<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Profile\ProfileWallMedia;
use App\ApiResource\Teacher\Public\TeacherProfileAvailability;
use App\ApiResource\Teacher\Public\TeacherProfileInstrument;
use App\ApiResource\Teacher\Public\TeacherProfilePricing;

/** What the profile page says about the member as a teacher (#1317): enough for « Prof de guitare, dès 25 € ». */
class PublicProfileTeacher
{
    /** @var TeacherProfileInstrument[] */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    public bool $offersTrial = false;

    /** In cents; null or 0 is a free trial. */
    public ?int $trialPrice = null;

    /** @var TeacherProfilePricing[] prices in cents */
    #[ApiProperty(genId: false)]
    public array $pricing = [];

    /** @var ProfileWallMedia[] in the order the member set, for « Écouter » */
    #[ApiProperty(genId: false)]
    public array $media = [];

    /** @var TeacherProfileAvailability[] weekly slots, as on the lessons page */
    #[ApiProperty(genId: false)]
    public array $availability = [];
}

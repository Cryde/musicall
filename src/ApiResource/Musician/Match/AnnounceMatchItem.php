<?php

declare(strict_types=1);

namespace App\ApiResource\Musician\Match;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Search\AnnounceMusician;

/** One match: the announce as the search shows it, the member's announce it answers, and why. */
class AnnounceMatchItem
{
    #[ApiProperty(genId: false)]
    public AnnounceMusician $announce;

    #[ApiProperty(genId: false)]
    public AnsweredAnnounce $answered;

    /** @var list<string> */
    public array $sharedStyles = [];
}

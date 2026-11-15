<?php

declare(strict_types=1);

namespace App\Enum\Metric;

/** What a vote request did, so a caller can react to it (#1081). */
enum VoteOutcome
{
    /** A first vote from this voter. */
    case Cast;

    /** The voter turned their vote the other way. */
    case Switched;

    /** The same vote sent again, which removes it. */
    case Withdrawn;
}

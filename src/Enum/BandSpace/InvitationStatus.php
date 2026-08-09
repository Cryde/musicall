<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    /** Cancelled by an admin, as opposed to Expired which is the clock running out on its own. */
    case Revoked = 'revoked';
}

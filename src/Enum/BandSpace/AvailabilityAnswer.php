<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * A member's answer to "Tu es disponible ?" for one date (#1000). No answer is the absence of a row,
 * which is the state the band needs to chase, so it is deliberately not a case here.
 */
enum AvailabilityAnswer: string
{
    case Yes = 'yes';
    case No = 'no';
}

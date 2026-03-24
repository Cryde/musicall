<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum MembershipStatus: string
{
    case Active = 'active';
    case Left = 'left';
    case Kicked = 'kicked';
}

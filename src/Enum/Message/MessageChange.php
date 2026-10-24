<?php

declare(strict_types=1);

namespace App\Enum\Message;

/**
 * What happened to a message already posted, in a band channel (#1056) or a direct conversation
 * (#1245). Carried by the live signal so a client can tell a pin, which also moves the « infos
 * importantes » bar, from a change that only touches the row.
 */
enum MessageChange: string
{
    case Reaction = 'reaction';
    case Edit = 'edit';
    case Delete = 'delete';
    case Pin = 'pin';
}

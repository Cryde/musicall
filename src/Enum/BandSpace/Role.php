<?php

declare(strict_types=1);

namespace App\Enum\BandSpace;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}

<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum TaskPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}

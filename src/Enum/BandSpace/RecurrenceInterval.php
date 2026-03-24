<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum RecurrenceInterval: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
}

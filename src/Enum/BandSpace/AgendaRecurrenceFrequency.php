<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum AgendaRecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}

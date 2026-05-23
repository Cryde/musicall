<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum AgendaRecurrenceMonthlyMode: string
{
    case ByDate = 'by_date';
    case ByWeekday = 'by_weekday';
}

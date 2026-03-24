<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum FinanceEntryScope: string
{
    case Band = 'band';
    case Personal = 'personal';
}

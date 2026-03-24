<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum FinanceEntryType: string
{
    case Expense = 'expense';
    case Income = 'income';
}

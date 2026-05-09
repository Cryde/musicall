<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\FinanceEntry;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<FinanceEntry>
 */
final class FinanceEntryFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'category' => FinanceCategoryFactory::new(),
            'label' => self::faker()->sentence(3),
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => self::faker()->numberBetween(1000, 100000),
            'date' => self::faker()->dateTime(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return FinanceEntry::class;
    }
}

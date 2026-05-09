<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<FinanceRecurrence>
 */
final class FinanceRecurrenceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'category' => FinanceCategoryFactory::new(),
            'label' => self::faker()->sentence(3),
            'type' => FinanceEntryType::Expense,
            'scope' => FinanceEntryScope::Band,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => self::faker()->numberBetween(1000, 50000),
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2024-12-31'),
            'isActive' => true,
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return FinanceRecurrence::class;
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\FinanceEntrySplit;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<FinanceEntrySplit>
 */
final class FinanceEntrySplitFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'entry' => FinanceEntryFactory::new(),
            'member' => BandSpaceMembershipFactory::new(),
            'amount' => self::faker()->numberBetween(100, 10000),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return FinanceEntrySplit::class;
    }
}

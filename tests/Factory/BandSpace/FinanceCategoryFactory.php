<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\FinanceCategory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<FinanceCategory>
 */
final class FinanceCategoryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'name' => self::faker()->sentence(2),
            'position' => 0,
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return FinanceCategory::class;
    }
}

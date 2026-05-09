<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\FinanceCategory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<FinanceCategory>
 */
final class FinanceCategoryFactory extends PersistentObjectFactory
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

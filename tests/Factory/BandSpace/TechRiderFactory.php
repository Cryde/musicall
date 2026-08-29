<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TechRider;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TechRider>
 */
final class TechRiderFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'name' => self::faker()->sentence(3),
            'createdBy' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return TechRider::class;
    }
}

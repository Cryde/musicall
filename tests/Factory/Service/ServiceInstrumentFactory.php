<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServiceInstrument;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ServiceInstrument>
 */
final class ServiceInstrumentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'service' => ServiceFactory::new()->teaching(),
            'instrument' => InstrumentFactory::new(),
        ];
    }

    public static function class(): string
    {
        return ServiceInstrument::class;
    }
}

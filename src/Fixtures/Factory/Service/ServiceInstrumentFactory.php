<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Service;

use App\Entity\Service\ServiceInstrument;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<ServiceInstrument>
 */
final class ServiceInstrumentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [];
    }

    public static function class(): string
    {
        return ServiceInstrument::class;
    }
}

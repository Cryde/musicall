<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServiceWork;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ServiceWork>
 */
final class ServiceWorkFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'service' => ServiceFactory::new()->soundEngineering(),
            'title' => 'Satellites',
            'position' => 0,
            'creationDatetime' => new DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ];
    }

    public static function class(): string
    {
        return ServiceWork::class;
    }
}

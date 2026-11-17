<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServicePlace;
use App\Enum\Service\ServicePlaceType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ServicePlace>
 */
final class ServicePlaceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'service' => ServiceFactory::new()->teaching(),
            'type' => ServicePlaceType::AtMyPlace,
        ];
    }

    /** @param array{city: string, latitude: string, longitude: string} $city */
    public function atMyPlace(array $city): self
    {
        return $this->with(['type' => ServicePlaceType::AtMyPlace, ...$city]);
    }

    /** @param array{city: string, latitude: string, longitude: string} $city */
    public function atClientPlace(array $city, ?int $radius): self
    {
        return $this->with(['type' => ServicePlaceType::AtClientPlace, 'radius' => $radius, ...$city]);
    }

    public function remote(): self
    {
        return $this->with(['type' => ServicePlaceType::Remote]);
    }

    public static function class(): string
    {
        return ServicePlace::class;
    }
}

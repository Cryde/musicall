<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\ServiceOffer;
use App\Enum\Service\OfferUnit;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ServiceOffer>
 */
final class ServiceOfferFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'service' => ServiceFactory::new()->teaching(),
            'unit' => OfferUnit::Lesson,
            'quantity' => 30,
            'price' => 2500,
        ];
    }

    public function lesson(int $minutes, int $priceInCents): self
    {
        return $this->with(['unit' => OfferUnit::Lesson, 'quantity' => $minutes, 'price' => $priceInCents]);
    }

    public function package(?int $lessons, int $priceInCents): self
    {
        return $this->with(['unit' => OfferUnit::Package, 'quantity' => $lessons, 'price' => $priceInCents]);
    }

    public static function class(): string
    {
        return ServiceOffer::class;
    }
}

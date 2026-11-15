<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\Service;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\ServiceKind;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Service>
 */
final class ServiceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'creationDatetime' => new DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ];
    }

    /**
     * A teaching service. Its details are given as TeachingDetails' own fields (`offersTrial`, `studentLevels`...),
     * next to the service's, and land in `details`.
     */
    public function teaching(): self
    {
        return $this->with(['kind' => ServiceKind::Teaching])
            ->beforeInstantiate(static function (array $attributes): array {
                $details = new TeachingDetails();
                foreach (array_keys(get_object_vars($details)) as $field) {
                    if (array_key_exists($field, $attributes)) {
                        $details->{$field} = $attributes[$field];
                        unset($attributes[$field]);
                    }
                }
                $attributes['details'] = $details->toArray();

                return $attributes;
            });
    }

    public function withTrial(int $priceInCents): self
    {
        return $this->with([
            'offersTrial' => true,
            'trialPrice' => $priceInCents,
        ]);
    }

    public static function class(): string
    {
        return Service::class;
    }
}

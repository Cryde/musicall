<?php

declare(strict_types=1);

namespace App\Tests\Factory\Service;

use App\Entity\Service\DeliveryDetails;
use App\Entity\Service\Service;
use App\Entity\Service\SoundEngineeringDetails;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServiceStatus;
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
            'status' => ServiceStatus::Published,
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
        return $this->ofKind(ServiceKind::Teaching);
    }

    /** A sound engineering service (#1410), its details given as SoundEngineeringDetails' own fields, like teaching(). */
    public function soundEngineering(): self
    {
        return $this->ofKind(ServiceKind::SoundEngineering);
    }

    /** A photography service (#1411), its details given as DeliveryDetails' own fields, like teaching(). */
    public function photography(): self
    {
        return $this->ofKind(ServiceKind::Photography);
    }

    /** A video service (#1411), its details given as DeliveryDetails' own fields, like teaching(). */
    public function video(): self
    {
        return $this->ofKind(ServiceKind::Video);
    }

    /** A graphic design service (#1412), its details given as DeliveryDetails' own fields, like teaching(). */
    public function graphicDesign(): self
    {
        return $this->ofKind(ServiceKind::GraphicDesign);
    }

    /** A web service (#1412), its details given as DeliveryDetails' own fields, like teaching(). */
    public function web(): self
    {
        return $this->ofKind(ServiceKind::Web);
    }

    public function withTrial(int $priceInCents): self
    {
        return $this->with([
            'offersTrial' => true,
            'trialPrice' => $priceInCents,
        ]);
    }

    /** A service of the kind, its details given as its details class's own fields, like teaching(). */
    public function ofKind(ServiceKind $kind): self
    {
        return $this->with(['kind' => $kind])
            ->beforeInstantiate(static function (array $attributes) use ($kind): array {
                $details = match ($kind) {
                    ServiceKind::Teaching => new TeachingDetails(),
                    ServiceKind::SoundEngineering => new SoundEngineeringDetails(),
                    ServiceKind::Photography, ServiceKind::Video, ServiceKind::GraphicDesign, ServiceKind::Web => new DeliveryDetails(),
                };
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

    public static function class(): string
    {
        return Service::class;
    }
}

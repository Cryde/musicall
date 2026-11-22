<?php

declare(strict_types=1);

namespace App\Entity\Service;

/**
 * What a photography or video service says it delivers and when (#1411), « 40 photos retouchées sous 7 jours »,
 * stored in Service::$details. Reading tolerates a missing key, like SoundEngineeringDetails.
 */
final class DeliveryDetails
{
    public const int MAX_DELIVERABLE_LENGTH = 100;

    /** In days. */
    public ?int $deliveryDays = null;

    /** « 40 photos retouchées ». */
    public ?string $deliverable = null;

    public static function of(Service $service): self
    {
        $stored = $service->details;
        $details = new self();
        $details->deliveryDays = $stored['delivery_days'] ?? null;
        $details->deliverable = $stored['deliverable'] ?? null;

        return $details;
    }

    /** @return array<string, mixed> what Service::$details stores */
    public function toArray(): array
    {
        return [
            'delivery_days' => $this->deliveryDays,
            'deliverable' => $this->deliverable,
        ];
    }

    public function isFilledIn(): bool
    {
        return $this->deliveryDays !== null || $this->deliverable !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Service;

/**
 * What only a sound engineering service shows (#1410), stored in Service::$details. Reading tolerates a missing key,
 * so a field added later needs no migration of the rows already stored.
 */
final class SoundEngineeringDetails
{
    public const int MAX_GEAR = 30;

    /** « 5 jours ouvrés »: the usual turnaround, in working days. */
    public ?int $turnaroundDays = null;

    public ?int $revisionsIncluded = null;

    /** @var list<string> DeliveryFormat values */
    public array $formats = [];

    /** @var list<string> « Matériel », short free tags in the engineer's order */
    public array $gear = [];

    public static function of(Service $service): self
    {
        $stored = $service->details;
        $details = new self();
        $details->turnaroundDays = $stored['turnaround_days'] ?? null;
        $details->revisionsIncluded = $stored['revisions_included'] ?? null;
        $details->formats = $stored['formats'] ?? [];
        $details->gear = $stored['gear'] ?? [];

        return $details;
    }

    /** @return array<string, mixed> what Service::$details stores */
    public function toArray(): array
    {
        return [
            'turnaround_days' => $this->turnaroundDays,
            'revisions_included' => $this->revisionsIncluded,
            'formats' => $this->formats,
            'gear' => $this->gear,
        ];
    }
}

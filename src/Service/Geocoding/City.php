<?php

declare(strict_types=1);

namespace App\Service\Geocoding;

/** A place a location field can offer, as the front end's city pickers expect it. */
final readonly class City
{
    public function __construct(
        public string $name,
        public string $context,
        public float $latitude,
        public float $longitude,
        public string $fullName,
    ) {
    }
}

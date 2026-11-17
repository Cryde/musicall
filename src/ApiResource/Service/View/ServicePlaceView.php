<?php

declare(strict_types=1);

namespace App\ApiResource\Service\View;

/** Where a service happens, as a visitor reads it: the city, never the address. */
class ServicePlaceView
{
    /** A ServicePlaceType value. */
    public string $type;

    public ?string $city = null;

    /** In km, how far the member travels for `at_client_place`. */
    public ?int $radius = null;
}

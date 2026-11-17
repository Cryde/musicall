<?php

declare(strict_types=1);

namespace App\ApiResource\Service\View;

class ServiceOfferView
{
    public string $id;

    /** An OfferUnit value. */
    public string $unit;

    public ?string $title = null;

    public ?string $description = null;

    /** In cents. */
    public int $price;
}

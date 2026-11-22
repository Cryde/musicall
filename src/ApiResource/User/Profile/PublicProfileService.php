<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

/** A service as the profile lists it and its wall line shows it (#1415). */
class PublicProfileService
{
    public string $id;

    /** A ServiceKind value. */
    public string $kind;

    /** A ServiceStatus value: a draft only ever reaches its owner. */
    public string $status;

    public ?string $headline = null;

    /** Its page, in the reader's locale. */
    public string $url;

    /** Its lowest price in cents, in the unit its kind quotes, or the cheapest of any unit without one. */
    public ?int $price = null;

    /** The OfferUnit value of that price. */
    public ?string $priceUnit = null;

    /** How much of the unit the price buys: a lesson's minutes, a package's lessons. */
    public ?int $priceQuantity = null;
}

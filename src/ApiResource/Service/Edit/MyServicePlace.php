<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Edit;

use App\Enum\Service\ServicePlaceType;
use Symfony\Component\Validator\Constraints as Assert;

/** A place, as its owner edits it: with the address the page never shows. */
class MyServicePlace
{
    #[Assert\NotBlank(message: 'service.place.type_required')]
    #[Assert\Choice(callback: [ServicePlaceType::class, 'values'], message: 'service.place.type_invalid')]
    public ?string $type = null;

    #[Assert\Length(max: 255)]
    public ?string $address = null;

    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\Length(max: 100)]
    public ?string $country = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    /** In km, how far the member travels for `at_client_place`. */
    #[Assert\Range(notInRangeMessage: 'service.place.radius_invalid', min: 1, max: 500)]
    public ?int $radius = null;
}

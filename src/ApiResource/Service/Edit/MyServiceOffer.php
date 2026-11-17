<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Edit;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** One of « Formules ». Its unit is checked against the kind by the resource holding it. */
class MyServiceOffer
{
    /** Read only: a save replaces every offer. */
    #[ApiProperty(writable: false)]
    public ?string $id = null;

    #[Assert\NotBlank(message: 'service.offer.unit_required')]
    public ?string $unit = null;

    #[Assert\NotBlank(message: 'service.offer.title_required')]
    #[Assert\Length(max: 100)]
    public ?string $title = null;

    #[Assert\Length(max: 255)]
    public ?string $description = null;

    /** In cents. */
    #[Assert\NotNull(message: 'service.offer.price_required')]
    #[Assert\Range(notInRangeMessage: 'service.offer.price_invalid', min: 0, max: 10_000_000)]
    public ?int $price = null;
}

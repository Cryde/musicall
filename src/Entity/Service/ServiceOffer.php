<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Service\OfferUnit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A price, and the unit it buys: every kind's « dès 25 € » and price sort read these rows (#1318). */
#[ORM\Entity]
#[ORM\Table(name: 'service_offer')]
class ServiceOffer
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: OfferUnit::class)]
    public OfferUnit $unit;

    /** How many of the unit, see OfferUnit. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $quantity = null;

    /** In cents. */
    #[ORM\Column(type: Types::INTEGER)]
    public int $price;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;
}

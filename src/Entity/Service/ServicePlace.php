<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Service\ServicePlaceType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** Where the service happens: at the member's place, at the client's within a radius, or remotely. */
#[ORM\Entity]
#[ORM\Table(name: 'service_place')]
class ServicePlace
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'places')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ServicePlaceType::class)]
    public ServicePlaceType $type;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $country = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $latitude = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $longitude = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $radius = null;
}

<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Entity\Attribute\Instrument;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'service_instrument')]
#[ORM\UniqueConstraint(name: 'unique_service_instrument', columns: ['service_id', 'instrument_id'])]
class ServiceInstrument
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'instruments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Instrument $instrument;
}

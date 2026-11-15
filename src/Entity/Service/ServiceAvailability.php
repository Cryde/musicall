<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Teacher\DayOfWeek;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A weekly slot. */
#[ORM\Entity]
#[ORM\Table(name: 'service_availability')]
class ServiceAvailability
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'availability')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: DayOfWeek::class)]
    public DayOfWeek $dayOfWeek;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    public \DateTimeImmutable $startTime;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    public \DateTimeImmutable $endTime;
}

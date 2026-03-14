<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Enum\Teacher\LocationType;
use App\Repository\Teacher\TeacherProfileLocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TeacherProfileLocationRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_location')]
class TeacherProfileLocation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: LocationType::class)]
    public LocationType $type;

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

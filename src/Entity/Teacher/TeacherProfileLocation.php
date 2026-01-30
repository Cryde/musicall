<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Enum\Teacher\LocationType;
use App\Repository\Teacher\TeacherProfileLocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: TeacherProfileLocationRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_location')]
class TeacherProfileLocation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: LocationType::class)]
    private LocationType $type;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $radius = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTeacherProfile(): TeacherProfile
    {
        return $this->teacherProfile;
    }

    public function setTeacherProfile(TeacherProfile $teacherProfile): self
    {
        $this->teacherProfile = $teacherProfile;

        return $this;
    }

    public function getType(): LocationType
    {
        return $this->type;
    }

    public function setType(LocationType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getRadius(): ?int
    {
        return $this->radius;
    }

    public function setRadius(?int $radius): self
    {
        $this->radius = $radius;

        return $this;
    }
}

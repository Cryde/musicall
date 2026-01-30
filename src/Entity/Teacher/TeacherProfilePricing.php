<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Enum\Teacher\SessionDuration;
use App\Repository\Teacher\TeacherProfilePricingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: TeacherProfilePricingRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_pricing')]
#[ORM\UniqueConstraint(name: 'teacher_profile_duration_unique', columns: ['teacher_profile_id', 'duration'])]
class TeacherProfilePricing
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'pricing')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: SessionDuration::class)]
    private SessionDuration $duration;

    #[ORM\Column(type: Types::INTEGER)]
    private int $price;

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

    public function getDuration(): SessionDuration
    {
        return $this->duration;
    }

    public function setDuration(SessionDuration $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Repository\Teacher\TeacherProfilePackageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: TeacherProfilePackageRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_package')]
class TeacherProfilePackage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'packages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $sessionsCount = null;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSessionsCount(): ?int
    {
        return $this->sessionsCount;
    }

    public function setSessionsCount(?int $sessionsCount): self
    {
        $this->sessionsCount = $sessionsCount;

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

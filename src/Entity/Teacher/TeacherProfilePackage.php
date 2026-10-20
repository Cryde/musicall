<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Repository\Teacher\TeacherProfilePackageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: TeacherProfilePackageRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_package')]
class TeacherProfilePackage
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'packages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 100)]
    public string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $sessionsCount = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $price;
}

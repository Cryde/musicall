<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Enum\Teacher\DayOfWeek;
use App\Repository\Teacher\TeacherAvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: TeacherAvailabilityRepository::class)]
#[ORM\Table(name: 'user_teacher_availability')]
class TeacherAvailability
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'availability')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: DayOfWeek::class)]
    public DayOfWeek $dayOfWeek;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    public \DateTimeImmutable $startTime;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    public \DateTimeImmutable $endTime;
}

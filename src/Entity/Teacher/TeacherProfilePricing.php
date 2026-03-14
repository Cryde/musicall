<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Enum\Teacher\SessionDuration;
use App\Repository\Teacher\TeacherProfilePricingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TeacherProfilePricingRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_pricing')]
#[ORM\UniqueConstraint(name: 'teacher_profile_duration_unique', columns: ['teacher_profile_id', 'duration'])]
class TeacherProfilePricing
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

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'pricing')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: SessionDuration::class)]
    public SessionDuration $duration;

    #[ORM\Column(type: Types::INTEGER)]
    public int $price;
}

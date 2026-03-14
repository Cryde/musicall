<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Entity\Attribute\Instrument;
use App\Repository\Teacher\TeacherProfileInstrumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TeacherProfileInstrumentRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_instrument')]
#[ORM\UniqueConstraint(name: 'teacher_profile_instrument_unique', columns: ['teacher_profile_id', 'instrument_id'])]
class TeacherProfileInstrument
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

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'instruments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Instrument $instrument;
}

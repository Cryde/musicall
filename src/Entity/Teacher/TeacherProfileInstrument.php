<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Entity\Attribute\Instrument;
use App\Repository\Teacher\TeacherProfileInstrumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: TeacherProfileInstrumentRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_instrument')]
#[ORM\UniqueConstraint(name: 'teacher_profile_instrument_unique', columns: ['teacher_profile_id', 'instrument_id'])]
class TeacherProfileInstrument
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'instruments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Instrument $instrument;
}

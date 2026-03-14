<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Enum\Musician\MediaPlatform;
use App\Repository\Teacher\TeacherProfileMediaRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TeacherProfileMediaRepository::class)]
#[ORM\Table(name: 'user_teacher_profile_media')]
class TeacherProfileMedia
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

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TeacherProfile $teacherProfile;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MediaPlatform::class)]
    public MediaPlatform $platform;

    #[ORM\Column(type: Types::STRING, length: 500)]
    public string $url;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $embedId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $thumbnailImageName = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}

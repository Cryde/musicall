<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Musician\MediaPlatform;
use App\Repository\Service\ServiceMediaRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A YouTube, SoundCloud or Spotify embed. */
#[ORM\Entity(repositoryClass: ServiceMediaRepository::class)]
#[ORM\Table(name: 'service_media')]
class ServiceMedia
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

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

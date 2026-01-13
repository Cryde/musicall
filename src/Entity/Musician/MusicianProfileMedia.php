<?php

declare(strict_types=1);

namespace App\Entity\Musician;

use App\Enum\Musician\MediaPlatform;
use App\Repository\Musician\MusicianProfileMediaRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: MusicianProfileMediaRepository::class)]
#[ORM\Table(name: 'user_musician_profile_media')]
class MusicianProfileMedia
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: MusicianProfile::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MusicianProfile $musicianProfile;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MediaPlatform::class)]
    private MediaPlatform $platform;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $url;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $embedId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $thumbnailImageName = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getMusicianProfile(): MusicianProfile
    {
        return $this->musicianProfile;
    }

    public function setMusicianProfile(MusicianProfile $musicianProfile): self
    {
        $this->musicianProfile = $musicianProfile;

        return $this;
    }

    public function getPlatform(): MediaPlatform
    {
        return $this->platform;
    }

    public function setPlatform(MediaPlatform $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getEmbedId(): string
    {
        return $this->embedId;
    }

    public function setEmbedId(string $embedId): self
    {
        $this->embedId = $embedId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getThumbnailImageName(): ?string
    {
        return $this->thumbnailImageName;
    }

    public function setThumbnailImageName(?string $thumbnailImageName): self
    {
        $this->thumbnailImageName = $thumbnailImageName;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCreationDatetime(): DateTimeImmutable
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeImmutable $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }
}

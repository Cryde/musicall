<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceFileRepository::class)]
#[ORM\Table(name: 'band_space_file')]
#[ORM\Index(columns: ['band_space_id', 'archive_datetime'], name: 'idx_band_space_file_band_archived')]
#[ORM\Index(columns: ['attached_source_type', 'attached_source_id'], name: 'idx_band_space_file_attached_source')]
class BandSpaceFile
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

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $createdBy = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $originalName;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    public ?string $attachedSourceType = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    public UuidInterface|string|null $attachedSourceId = null {
        get {
            return is_string($this->attachedSourceId)
                ? $this->attachedSourceId
                : $this->attachedSourceId?->toString();
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $archiveDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

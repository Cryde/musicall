<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\SongRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\Table(name: 'band_space_song')]
#[ORM\Index(name: 'idx_song_band_archive', columns: ['band_space_id', 'archive_datetime'])]
class Song
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $tempo = null;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    public ?string $tonality = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $referenceDuration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $notes = null;

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

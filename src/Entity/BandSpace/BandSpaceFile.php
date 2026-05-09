<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceFileRepository::class)]
#[ORM\Table(name: 'band_space_file')]
#[ORM\Index(columns: ['band_space_id', 'archive_datetime'], name: 'idx_band_space_file_band_archived')]
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

    #[ORM\ManyToOne(targetEntity: BandSpaceFolder::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?BandSpaceFolder $folder = null;

    #[ORM\ManyToOne(targetEntity: BandSpaceFileVersion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?BandSpaceFileVersion $currentVersion = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $originalName;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $archiveDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    /**
     * @var Collection<int, BandSpaceFileTag>
     */
    #[ORM\ManyToMany(targetEntity: BandSpaceFileTag::class)]
    #[ORM\JoinTable(name: 'band_space_file_to_tag')]
    public Collection $tags;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->tags = new ArrayCollection();
    }
}

<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\SetlistRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: SetlistRepository::class)]
#[ORM\Table(name: 'band_space_setlist')]
#[ORM\Index(name: 'idx_setlist_band_archive', columns: ['band_space_id', 'archive_datetime'])]
class Setlist
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    /** How long the band means the set to last, in seconds (#1061). */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $targetDuration = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $archiveDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    /** @var Collection<int, SetlistItem> */
    #[ORM\OneToMany(targetEntity: SetlistItem::class, mappedBy: 'setlist', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => SortDirection::Ascending])]
    public Collection $items;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->items = new ArrayCollection();
    }
}

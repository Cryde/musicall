<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\TechRiderRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * A technical rider: the document a band sends to a venue before a show.
 *
 * Unlike every other Band Space document this one is read by someone outside the band,
 * which is why it carries its author and why archiving is preferred to deletion.
 */
#[ORM\Entity(repositoryClass: TechRiderRepository::class)]
#[ORM\Table(name: 'band_space_tech_rider')]
#[ORM\Index(name: 'idx_tech_rider_band_archive', columns: ['band_space_id', 'archive_datetime'])]
class TechRider
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

    /**
     * Nullable so deleting the author does not take the rider with them: the band still
     * needs the document. Same shape as BandSpaceFile::$createdBy.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $createdBy = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $archiveDatetime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    /** @var Collection<int, TechRiderItem> */
    #[ORM\OneToMany(targetEntity: TechRiderItem::class, mappedBy: 'techRider', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $items;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->items = new ArrayCollection();
    }

    public function isArchived(): bool
    {
        return $this->archiveDatetime instanceof DateTimeImmutable;
    }
}

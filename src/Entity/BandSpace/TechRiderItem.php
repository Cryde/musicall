<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\TechRiderItemRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One titled block of a rider's written body.
 *
 * Items are user-created and user-titled rather than a fixed set of fields, because a
 * real rider carries things no fixed form predicts (mixing desk requirements, guest passes,
 * a crew list). Order is meaningful: it is the order the venue reads them in.
 */
#[ORM\Entity(repositoryClass: TechRiderItemRepository::class)]
#[ORM\Table(name: 'band_space_tech_rider_item')]
#[ORM\Index(name: 'idx_rider_item_rider_position', columns: ['tech_rider_id', 'position'])]
class TechRiderItem
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

    #[ORM\ManyToOne(targetEntity: TechRider::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TechRider $techRider;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TechRiderItemType::class)]
    public TechRiderItemType $type = TechRiderItemType::Text;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    /**
     * Whether this item appears in the composed document. Excluding is not deleting: a
     * festival stage plot stays authored and editable while a club rider leaves it out.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $isIncluded = true;

    /**
     * The page itself, for a Document item: a file the band already has in its files area
     * rather than a second upload silo, so folders, versions, tags and quota keep working.
     *
     * SET NULL rather than cascade: deleting the file must not silently delete a page of the
     * rider. The item survives, empty, and says so.
     */
    #[ORM\ManyToOne(targetEntity: BandSpaceFile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?BandSpaceFile $file = null;

    /**
     * Payload for the types that store a document: a TipTap doc for Text, the plot for
     * StagePlot. Null until written in, which is how seeded items start. Types backed by
     * their own tables leave it null.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $content = null;

    /**
     * The rows of a PatchList item.
     *
     * Deliberately no cascade and no orphanRemoval, unlike TechRider::$items. Rows are written
     * only by the replace procedure, which deletes the old set in one bulk query and then resets
     * this collection to the new rows. With orphanRemoval that reset would not be a reset:
     * PersistentCollection::clear() initialises the collection and schedules every element it
     * finds for deletion, so each row would be deleted twice, once in bulk and once by id.
     *
     * Deleting the item still takes its rows with it, through the database's ON DELETE CASCADE.
     *
     * @var Collection<int, TechRiderPatchRow>
     */
    #[ORM\OneToMany(targetEntity: TechRiderPatchRow::class, mappedBy: 'item')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $patchRows;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->patchRows = new ArrayCollection();
    }
}

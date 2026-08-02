<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\TechRiderColour;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Repository\BandSpace\TechRiderPatchRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One line of a patch list: a source, the channel it lands on, the microphone it needs and
 * where it is routed.
 *
 * Relational rather than a blob in the item's `content`, because the data is genuinely tabular:
 * uniform rows, short fields with real length limits, read in a fixed order. The item's
 * `content` stays null for this type.
 *
 * Rows hang off the item, not the rider, so a rider can carry two patch lists. An electric set
 * and an acoustic set need different ones, and the band decides where each sits in the export.
 */
#[ORM\Entity(repositoryClass: TechRiderPatchRowRepository::class)]
#[ORM\Table(name: 'band_space_tech_rider_patch_row')]
#[ORM\Index(name: 'idx_patch_row_item_dir_pos', columns: ['tech_rider_item_id', 'direction', 'position'])]
class TechRiderPatchRow
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

    /** Named in full rather than item_id: "item" alone does not say item of what. */
    #[ORM\ManyToOne(targetEntity: TechRiderItem::class, inversedBy: 'patchRows')]
    #[ORM\JoinColumn(name: 'tech_rider_item_id', nullable: false, onDelete: 'CASCADE')]
    public TechRiderItem $item;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: TechRiderPatchDirection::class)]
    public TechRiderPatchDirection $direction = TechRiderPatchDirection::Input;

    /** The printed "Track" number. Not the position: a list may legitimately start at 1 and skip. */
    #[ORM\Column(type: Types::SMALLINT)]
    public int $channel = 1;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    public ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    public ?string $microphone = null;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    public ?string $routing = null;

    /**
     * Groups rows by destination, which is how the split is read at a glance. Null is an
     * ungrouped row, not a missing value.
     */
    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, enumType: TechRiderColour::class)]
    public ?TechRiderColour $colour = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;
}

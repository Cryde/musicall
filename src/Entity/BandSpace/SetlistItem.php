<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\SetlistItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: SetlistItemRepository::class)]
#[ORM\Table(name: 'band_space_setlist_item')]
#[ORM\Index(name: 'idx_setlist_item_setlist_position', columns: ['setlist_id', 'position'])]
class SetlistItem
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Setlist::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Setlist $setlist;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: false, enumType: SetlistItemType::class)]
    public SetlistItemType $type;

    #[ORM\ManyToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Song $song = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $label = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $durationOverride = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $note = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    public ?string $transition = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;
}

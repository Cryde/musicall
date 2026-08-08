<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\BandSpaceNoteRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: BandSpaceNoteRepository::class)]
#[ORM\Table(name: 'band_space_note')]
class BandSpaceNote
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

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public ?self $parent = null;

    /**
     * @var Collection<int, BandSpaceNote>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $children;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    public ?string $emoji = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $content = null;

    /**
     * The revision number of the body, bumped by every write that actually changes `content`.
     *
     * A save carries the revision it was written against, so a member editing a copy that went stale
     * is refused instead of silently replacing whatever another member wrote in the meantime. That
     * matters here because the body is written by an autosave timer, so neither member ever chose to
     * save and there is no history to recover from.
     *
     * It is deliberately not a Doctrine `#[ORM\Version]` column: that would bump on a title, emoji or
     * position write too, and a rename made while someone is typing would then reject their next
     * autosave for no reason. Those are single field writes that cannot carry minutes of work.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    public int $contentVersion = 1;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->children = new ArrayCollection();
    }
}

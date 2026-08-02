<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\TechRiderSectionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One titled block of a rider's written body.
 *
 * Sections are user-created and user-titled rather than a fixed set of fields, because a
 * real rider carries things no fixed form predicts (mixing desk requirements, guest passes,
 * a crew list). Order is meaningful: it is the order the venue reads them in.
 */
#[ORM\Entity(repositoryClass: TechRiderSectionRepository::class)]
#[ORM\Table(name: 'band_space_tech_rider_section')]
#[ORM\Index(name: 'idx_rider_section_rider_position', columns: ['tech_rider_id', 'position'])]
class TechRiderSection
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

    #[ORM\ManyToOne(targetEntity: TechRider::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public TechRider $techRider;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    /**
     * TipTap document, the same shape BandSpaceNote::$content stores. Null until the section
     * is written in, which is how seeded sections start.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $content = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

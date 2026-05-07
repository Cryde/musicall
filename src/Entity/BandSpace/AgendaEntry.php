<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\AgendaEntryRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: AgendaEntryRepository::class)]
#[ORM\Table(name: 'agenda_entry')]
#[ORM\Index(columns: ['event_datetime'], name: 'idx_agenda_entry_event_datetime')]
class AgendaEntry
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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $creator = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $location = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $eventDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $endDatetime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isAllDay = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

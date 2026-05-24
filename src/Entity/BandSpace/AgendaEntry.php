<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use App\Repository\BandSpace\AgendaEntryRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: AgendaEntryRepository::class)]
#[ORM\Table(name: 'agenda_entry')]
#[ORM\Index(name: 'idx_agenda_entry_event_datetime', columns: ['event_datetime'])]
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

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: AgendaRecurrenceFrequency::class)]
    public ?AgendaRecurrenceFrequency $recurrenceFrequency = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $recurrenceUntilDate = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: AgendaRecurrenceMonthlyMode::class)]
    public ?AgendaRecurrenceMonthlyMode $recurrenceMonthlyMode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    /**
     * Cancelled occurrences for this recurring entry. Aggregator skips any
     * occurrence whose date matches one of these during expansion.
     *
     * @var Collection<int, AgendaEntryException>
     */
    #[ORM\OneToMany(targetEntity: AgendaEntryException::class, mappedBy: 'agendaEntry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $exceptions;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->exceptions = new ArrayCollection();
    }
}

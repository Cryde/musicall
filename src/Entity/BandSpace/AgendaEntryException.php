<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\AgendaEntryExceptionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * A single cancelled occurrence of a recurring AgendaEntry. AgendaAggregator
 * filters out any occurrence whose date matches one of these rows during
 * expansion. Designed to grow into a per-occurrence override table later
 * (add title / event_datetime / etc. nullable columns) without a data move.
 */
#[ORM\Entity(repositoryClass: AgendaEntryExceptionRepository::class)]
#[ORM\Table(name: 'agenda_entry_exception')]
#[ORM\UniqueConstraint(name: 'unq_agenda_entry_exception_date', columns: ['agenda_entry_id', 'occurrence_date'])]
class AgendaEntryException
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

    #[ORM\ManyToOne(targetEntity: AgendaEntry::class, inversedBy: 'exceptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public AgendaEntry $agendaEntry;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public DateTimeImmutable $occurrenceDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}

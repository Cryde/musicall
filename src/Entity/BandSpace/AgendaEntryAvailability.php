<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\AvailabilityAnswer;
use App\Repository\BandSpace\AgendaEntryAvailabilityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One member's answer for one occurrence of an agenda entry (#1000).
 *
 * The occurrence date is always set, a one-off entry included, so the unique constraint holds: MariaDB
 * treats NULLs as distinct, and a nullable date would let one member answer a one-off twice. It is
 * keyed like AgendaEntryException, the UTC date of the occurrence's start.
 */
#[ORM\Entity(repositoryClass: AgendaEntryAvailabilityRepository::class)]
#[ORM\Table(name: 'agenda_entry_availability')]
#[ORM\UniqueConstraint(name: 'unq_agenda_entry_availability', columns: ['agenda_entry_id', 'occurrence_date', 'membership_id'])]
class AgendaEntryAvailability
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

    #[ORM\ManyToOne(targetEntity: AgendaEntry::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public AgendaEntry $agendaEntry;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public DateTimeImmutable $occurrenceDate;

    #[ORM\ManyToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpaceMembership $membership;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: AvailabilityAnswer::class)]
    public AvailabilityAnswer $answer;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $answeredAt;
}

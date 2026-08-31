<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\MemberAbsenceRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * A period a band member is not available, so the calendar can answer "who can actually be there"
 * instead of the question being re-asked in a chat thread every time somebody proposes a date.
 *
 * Day granularity, not datetime: "I am away from 10 to 12 August" is the real unit, and a single
 * day is a range where start equals end. Half day availability is a rabbit hole, and the band level
 * question is only ever whether this person is around that day.
 */
#[ORM\Entity(repositoryClass: MemberAbsenceRepository::class)]
#[ORM\Table(name: 'band_space_member_absence')]
class MemberAbsence
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

    /**
     * The membership rather than the user, so an absence belongs to this band the way a finance
     * split does, and a member who later leaves does not drag their other bands' records along.
     * The band space is reached through `member->bandSpace`.
     */
    #[ORM\ManyToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpaceMembership $member;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public DateTimeImmutable $startDate;

    /** Inclusive: the member is away for the whole of this day too. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    public ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\AgendaFeedTokenRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * The secret half of one member's agenda iCal subscription URL.
 *
 * Hung off the membership rather than off the band space, so a leaked URL is revoked for one person
 * in one band. A member of several bands therefore holds several tokens, which their calendar client
 * renders as several separately coloured, separately toggleable calendars.
 *
 * `onDelete: CASCADE` is a safety net, not the revocation mechanism: a membership row survives
 * leaving and being kicked (it goes to `MembershipStatus::Left` / `Kicked` and keeps a
 * `leftDatetime`), so the cascade never fires on either. Revocation is the token row being deleted
 * by the leave and kick processors, backed by the feed provider refusing a membership that is no
 * longer active.
 */
#[ORM\Entity(repositoryClass: AgendaFeedTokenRepository::class)]
#[ORM\Table(name: 'band_space_agenda_feed_token')]
class AgendaFeedToken
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

    /**
     * Unique, so regenerating rotates the hash in place and a member never holds two live feeds for
     * the same band.
     */
    #[ORM\OneToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    public BandSpaceMembership $membership;

    /** sha256 of the plaintext token, which is never stored. */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    public string $tokenHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $lastAccessDatetime = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $accessCount = 0;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}

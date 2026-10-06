<?php declare(strict_types=1);

namespace App\Entity\Report;

use App\Entity\User;
use App\Enum\Report\ReportOutcome;
use App\Enum\Report\ReportReason;
use App\Enum\Report\ReportTargetType;
use App\Repository\Report\ReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * A user flagging content or another user to the moderators (#1116). The target is named by type and
 * id rather than a foreign key, and its text is copied at report time, so the evidence outlives an
 * edit or a deletion of the content.
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'report')]
#[ORM\Index(name: 'idx_report_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_report_resolution', columns: ['resolution_datetime'])]
class Report
{
    final public const int DETAILS_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $reporter;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ReportTargetType::class)]
    public ReportTargetType $targetType;

    /** A uuid for most targets, an integer as text for comments and publications. */
    #[ORM\Column(type: Types::STRING, length: 36)]
    public string $targetId;

    /** Who wrote the content, or the reported user; what a suspension acts on. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $targetAuthor = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ReportReason::class)]
    public ReportReason $reason;

    #[ORM\Column(type: Types::STRING, length: self::DETAILS_MAX_LENGTH, nullable: true)]
    public ?string $details = null;

    #[ORM\Column(type: Types::TEXT)]
    public string $snapshotText = '';

    /** @var array<string, scalar|null> */
    #[ORM\Column(type: Types::JSON)]
    public array $snapshotContext = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $resolutionDatetime = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $resolvedBy = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true, enumType: ReportOutcome::class)]
    public ?ReportOutcome $outcome = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return !$this->resolutionDatetime instanceof DateTimeImmutable;
    }
}

<?php declare(strict_types=1);

namespace App\Entity\Moderation;

use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Moderation\ModerationActionType;
use App\Repository\Moderation\ModerationActionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** One thing a moderator did, kept as the record of who did what and when (#1116). */
#[ORM\Entity(repositoryClass: ModerationActionRepository::class)]
#[ORM\Table(name: 'moderation_action')]
class ModerationAction
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $moderator;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ModerationActionType::class)]
    public ModerationActionType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $targetUser = null;

    #[ORM\ManyToOne(targetEntity: Report::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Report $report = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    public ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}

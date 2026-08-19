<?php

declare(strict_types=1);

namespace App\Entity\Forum;

use App\Entity\User;
use App\Repository\Forum\ForumPostReportRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: ForumPostReportRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_forum_post_report_post_reporter', columns: ['post_id', 'reporter_id'])]
class ForumPostReport
{
    final public const int REASON_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: ForumPost::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ForumPost $post;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $reporter;

    #[ORM\Column(type: Types::STRING, length: self::REASON_MAX_LENGTH)]
    public string $reason;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $resolvedDatetime = null;

    /**
     * The moderator who closed the report. SET NULL rather than CASCADE: deleting a moderator account
     * must not erase the moderation history of a post that is still online.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?User $resolvedBy = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function isResolved(): bool
    {
        return $this->resolvedDatetime instanceof DateTimeInterface;
    }
}

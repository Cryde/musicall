<?php declare(strict_types=1);

namespace App\Entity\User\Relation;

use App\Entity\User;
use App\Repository\User\Relation\UserBlockRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One user blocking another (#1117). One row per direction: two people may block each other, and
 * either can lift theirs without touching the other's.
 */
#[ORM\Entity(repositoryClass: UserBlockRepository::class)]
#[ORM\Table(name: 'user_block')]
#[ORM\UniqueConstraint(name: 'user_block_pair', columns: ['blocker_id', 'blocked_id'])]
class UserBlock
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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $blocker;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $blocked;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct(User $blocker, User $blocked)
    {
        $this->blocker = $blocker;
        $this->blocked = $blocked;
        $this->creationDatetime = new DateTimeImmutable();
    }
}

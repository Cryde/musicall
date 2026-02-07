<?php

declare(strict_types=1);

namespace App\Entity\Metric;

use App\Entity\User;
use App\Repository\Metric\VoteRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_vote_entity')]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VoteCache::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private VoteCache $voteCache;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $identifier;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $value;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVoteCache(): VoteCache
    {
        return $this->voteCache;
    }

    public function setVoteCache(VoteCache $voteCache): self
    {
        $this->voteCache = $voteCache;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getCreationDatetime(): DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }
}

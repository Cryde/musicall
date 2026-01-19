<?php

namespace App\Entity\Metric;

use DateTime;
use DateTimeInterface;
use App\Entity\User;
use App\Repository\Metric\ViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewRepository::class)]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_view_entity')]
class View
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $identifier;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ViewCache::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ViewCache $viewCache;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $entityId = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

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

    public function getViewCache(): ViewCache
    {
        return $this->viewCache;
    }

    public function setViewCache(ViewCache $viewCache): self
    {
        $this->viewCache = $viewCache;

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
}

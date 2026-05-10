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
#[ORM\Index(name: 'idx_vote_entity', columns: ['entity_type', 'entity_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VoteCache::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public VoteCache $voteCache;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $identifier;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $value;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    public ?string $entityType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    public ?string $entityId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

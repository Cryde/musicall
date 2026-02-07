<?php

declare(strict_types=1);

namespace App\Entity\Metric;

use App\Repository\Metric\VoteCacheRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteCacheRepository::class)]
class VoteCache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $upvoteCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $downvoteCount = 0;

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

    public function getUpvoteCount(): int
    {
        return $this->upvoteCount;
    }

    public function setUpvoteCount(int $upvoteCount): self
    {
        $this->upvoteCount = $upvoteCount;

        return $this;
    }

    public function getDownvoteCount(): int
    {
        return $this->downvoteCount;
    }

    public function setDownvoteCount(int $downvoteCount): self
    {
        $this->downvoteCount = $downvoteCount;

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

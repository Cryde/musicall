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
    public ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $upvoteCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    public int $downvoteCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

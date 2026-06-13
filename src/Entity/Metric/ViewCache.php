<?php

declare(strict_types=1);

namespace App\Entity\Metric;

use DateTime;
use App\Repository\Metric\ViewCacheRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// Internal view-counter entity; deliberately NOT an API Platform resource.
// It was previously exposed via an ungated GET /api/view_caches/{id} that
// nothing consumed (SECURITY-FIX.md finding 12).
#[ORM\Entity(repositoryClass: ViewCacheRepository::class)]
class ViewCache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public int $id;

    #[ORM\Column(type: Types::INTEGER)]
    public int $count = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

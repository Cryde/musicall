<?php

declare(strict_types=1);

namespace App\Entity\Metric;

use ApiPlatform\Metadata\Get;
use DateTime;
use App\Repository\Metric\ViewCacheRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ViewCacheRepository::class)]
#[Get(normalizationContext: ['groups' => ViewCache::ITEM])]
class ViewCache
{
    final const ITEM = 'VIEW_CACHE_ITEM';

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

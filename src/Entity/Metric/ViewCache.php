<?php

namespace App\Entity\Metric;

use ApiPlatform\Metadata\Get;
use App\Entity\Gallery;
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
    private int $id;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups([Gallery::LIST])]
    private int $count = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getCreationDatetime(): DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): ViewCache
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }
}

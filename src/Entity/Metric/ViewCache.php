<?php

namespace App\Entity\Metric;

use App\Repository\Metric\ViewCacheRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ViewCacheRepository::class)
 */
class ViewCache
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @ORM\Column(type="integer")
     */
    private $count;
    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDatetime;

    public function __construct()
    {
        $this->count = 0;
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getCreationDatetime(): \DateTime
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTime $creationDatetime): ViewCache
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }
}

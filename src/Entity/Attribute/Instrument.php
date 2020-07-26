<?php

namespace App\Entity\Attribute;

use App\Repository\Attribute\InstrumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @UniqueEntity("slug")
 * @UniqueEntity("name")
 * @UniqueEntity("musicianName")
 *
 * @ORM\Entity(repositoryClass=InstrumentRepository::class)
 * @ORM\Table(name="attribute_instrument")
 */
class Instrument
{
    /**
     * @ORM\Id()
     * @ORM\Column(name="id", type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @Assert\NotBlank()
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $musicianName;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $slug;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getMusicianName(): ?string
    {
        return $this->musicianName;
    }

    public function setMusicianName(string $musicianName): self
    {
        $this->musicianName = $musicianName;

        return $this;
    }
}

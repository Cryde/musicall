<?php

namespace App\Entity\Wiki;

use App\Repository\Wiki\ArtistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ArtistRepository::class)
 * @UniqueEntity(fields={"name"})
 */
class Artist
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     *
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $biography;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDatetime;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $members;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $labelName;

    /**
     * @ORM\OneToMany(targetEntity=ArtistSocial::class, mappedBy="artist")
     */
    private $socials;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $slug;


    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
        $this->socials = new ArrayCollection();
    }

    public function getId(): ?int
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

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): self
    {
        $this->biography = $biography;

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

    public function getMembers(): ?string
    {
        return $this->members;
    }

    public function setMembers(?string $members): self
    {
        $this->members = $members;

        return $this;
    }

    public function getLabelName(): ?string
    {
        return $this->labelName;
    }

    public function setLabelName(?string $labelName): self
    {
        $this->labelName = $labelName;

        return $this;
    }

    /**
     * @return Collection|ArtistSocial[]
     */
    public function getSocials(): Collection
    {
        return $this->socials;
    }

    public function addSocial(ArtistSocial $social): self
    {
        if (!$this->socials->contains($social)) {
            $this->socials[] = $social;
            $social->setArtist($this);
        }

        return $this;
    }

    public function removeSocial(ArtistSocial $social): self
    {
        if ($this->socials->contains($social)) {
            $this->socials->removeElement($social);
            // set the owning side to null (unless already changed)
            if ($social->getArtist() === $this) {
                $social->setArtist(null);
            }
        }

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
}

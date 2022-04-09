<?php

namespace App\Entity\Wiki;

use App\Entity\Image\WikiArtistCover;
use App\Repository\Wiki\ArtistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArtistRepository::class)]
#[UniqueEntity(fields: ['name'])]
#[UniqueEntity(fields: ['slug'])]
class Artist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private $id;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private $biography;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private $members;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private $labelName;

    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'artist', targetEntity: ArtistSocial::class, cascade: ['persist', 'remove'])]
    private $socials;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private $slug;

    #[ORM\OneToOne(targetEntity: WikiArtistCover::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $cover;

    /**
     * @Assert\AtLeastOneOf({
     *     @Assert\Blank(),
     *     @Assert\Country(alpha3=true)
     * })
     */
    #[ORM\Column(type: Types::STRING, length: 3, nullable: true)]
    private $countryCode;

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

    public function getCover(): ?WikiArtistCover
    {
        return $this->cover;
    }

    public function setCover(?WikiArtistCover $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }
}

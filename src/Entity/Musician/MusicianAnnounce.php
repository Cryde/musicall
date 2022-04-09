<?php

namespace App\Entity\Musician;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MusicianAnnounceRepository::class)]
class MusicianAnnounce
{
    const TYPE_MUSICIAN = 1;
    const TYPE_BAND = 2;
    const TYPES = [self::TYPE_MUSICIAN, self::TYPE_BAND];

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::GUID)]
    #[ORM\GeneratedValue(strategy: 'UUID')]
    private $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private $creationDatetime;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $author;

    #[Assert\Choice(choices: MusicianAnnounce::TYPES)]
    #[ORM\Column(type: Types::SMALLINT)]
    private $type;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $instrument;

    #[Assert\Length(min: 1)]
    #[ORM\ManyToMany(targetEntity: Style::class)]
    private $styles;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private $locationName;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private $longitude;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private $latitude;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private $note;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
        $this->styles = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getInstrument(): ?Instrument
    {
        return $this->instrument;
    }

    public function setInstrument(?Instrument $instrument): self
    {
        $this->instrument = $instrument;

        return $this;
    }

    /**
     * @return Collection|Style[]
     */
    public function getStyles(): Collection
    {
        return $this->styles;
    }

    public function addStyle(Style $style): self
    {
        if (!$this->styles->contains($style)) {
            $this->styles[] = $style;
        }

        return $this;
    }

    public function removeStyle(Style $style): self
    {
        if ($this->styles->contains($style)) {
            $this->styles->removeElement($style);
        }

        return $this;
    }

    public function getLocationName(): ?string
    {
        return $this->locationName;
    }

    public function setLocationName(string $locationName): self
    {
        $this->locationName = $locationName;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }
}

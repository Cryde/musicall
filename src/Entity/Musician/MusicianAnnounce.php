<?php

namespace App\Entity\Musician;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: MusicianAnnounceRepository::class)]
class MusicianAnnounce
{
    final const LIMIT_LAST_ANNOUNCES = 10;
    final const TYPE_MUSICIAN = 1;
    final const string TYPE_MUSICIAN_STR = '1';
    final const TYPE_BAND = 2;
    final const string TYPE_BAND_STR = '2';
    final const TYPES = [self::TYPE_MUSICIAN, self::TYPE_BAND];

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(type: Types::SMALLINT)]
    private $type;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Instrument $instrument;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    private Collection $styles;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $locationName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $longitude;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $latitude;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->styles = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreationDatetime(): ?DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
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

    public function getStyles(): Collection
    {
        return $this->styles;
    }

    public function setStyles(Collection $styles): static
    {
        $this->styles = $styles;

        return $this;
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

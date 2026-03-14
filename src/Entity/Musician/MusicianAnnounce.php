<?php

declare(strict_types=1);

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
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: MusicianAnnounceRepository::class)]
class MusicianAnnounce
{
    final const int LIMIT_LAST_ANNOUNCES = 6;
    final const TYPE_MUSICIAN = 1; // means announce search for a musician (so announce represent a band)
    final const string TYPE_MUSICIAN_STR = '1';
    final const TYPE_BAND = 2; // means announce search for a band (so announce represent a musician alone)
    final const string TYPE_BAND_STR = '2';
    final const array TYPES = [self::TYPE_MUSICIAN, self::TYPE_BAND];

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author;

    #[ORM\Column(type: Types::SMALLINT)]
    public int $type;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Instrument $instrument;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    public Collection $styles;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $locationName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $longitude;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $latitude;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $note = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->styles = new ArrayCollection();
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
}

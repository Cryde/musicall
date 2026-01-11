<?php

declare(strict_types=1);

namespace App\Entity\Musician;

use App\Entity\Attribute\Style;
use App\Entity\User;
use App\Enum\Musician\AvailabilityStatus;
use App\Repository\Musician\MusicianProfileRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: MusicianProfileRepository::class)]
#[ORM\Table(name: 'user_musician_profile')]
class MusicianProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\OneToOne(inversedBy: 'musicianProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: AvailabilityStatus::class)]
    private ?AvailabilityStatus $availabilityStatus = null;

    /**
     * @var Collection<int, MusicianProfileInstrument>
     */
    #[ORM\OneToMany(mappedBy: 'musicianProfile', targetEntity: MusicianProfileInstrument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $instruments;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    #[ORM\JoinTable(name: 'user_musician_profile_style')]
    private Collection $styles;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updateDatetime = null;

    public function __construct()
    {
        $this->instruments = new ArrayCollection();
        $this->styles = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAvailabilityStatus(): ?AvailabilityStatus
    {
        return $this->availabilityStatus;
    }

    public function setAvailabilityStatus(?AvailabilityStatus $availabilityStatus): self
    {
        $this->availabilityStatus = $availabilityStatus;

        return $this;
    }

    /**
     * @return Collection<int, MusicianProfileInstrument>
     */
    public function getInstruments(): Collection
    {
        return $this->instruments;
    }

    public function addInstrument(MusicianProfileInstrument $instrument): self
    {
        if (!$this->instruments->contains($instrument)) {
            $this->instruments->add($instrument);
            $instrument->setMusicianProfile($this);
        }

        return $this;
    }

    public function removeInstrument(MusicianProfileInstrument $instrument): self
    {
        $this->instruments->removeElement($instrument);

        return $this;
    }

    /**
     * @return Collection<int, Style>
     */
    public function getStyles(): Collection
    {
        return $this->styles;
    }

    public function addStyle(Style $style): self
    {
        if (!$this->styles->contains($style)) {
            $this->styles->add($style);
        }

        return $this;
    }

    public function removeStyle(Style $style): self
    {
        $this->styles->removeElement($style);

        return $this;
    }

    public function getCreationDatetime(): DateTimeImmutable
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeImmutable $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getUpdateDatetime(): ?DateTimeImmutable
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(?DateTimeImmutable $updateDatetime): self
    {
        $this->updateDatetime = $updateDatetime;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Musician;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\Attribute\Style;
use App\Entity\Metric\ViewCache;
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
#[ORM\UniqueConstraint(name: 'unique_musician_profile_user', columns: ['user_id'])]
class MusicianProfile implements ViewableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\OneToOne(inversedBy: 'musicianProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, enumType: AvailabilityStatus::class)]
    public ?AvailabilityStatus $availabilityStatus = null;

    /**
     * @var Collection<int, MusicianProfileInstrument>
     */
    #[ORM\OneToMany(mappedBy: 'musicianProfile', targetEntity: MusicianProfileInstrument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $instruments;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    #[ORM\JoinTable(name: 'user_musician_profile_style')]
    public Collection $styles;

    /**
     * @var Collection<int, MusicianProfileMedia>
     */
    #[ORM\OneToMany(mappedBy: 'musicianProfile', targetEntity: MusicianProfileMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $media;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updateDatetime = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    public ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->instruments = new ArrayCollection();
        $this->styles = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function addInstrument(MusicianProfileInstrument $instrument): self
    {
        if (!$this->instruments->contains($instrument)) {
            $this->instruments->add($instrument);
            $instrument->musicianProfile = $this;
        }

        return $this;
    }

    public function removeInstrument(MusicianProfileInstrument $instrument): self
    {
        $this->instruments->removeElement($instrument);

        return $this;
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

    public function addMedia(MusicianProfileMedia $media): self
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->musicianProfile = $this;
        }

        return $this;
    }

    public function removeMedia(MusicianProfileMedia $media): self
    {
        $this->media->removeElement($media);

        return $this;
    }

    public function getViewableId(): ?string
    {
        return $this->id;
    }

    public function getViewableType(): string
    {
        return 'musician_profile';
    }
}

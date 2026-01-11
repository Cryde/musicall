<?php

declare(strict_types=1);

namespace App\Entity\Musician;

use App\Entity\Attribute\Instrument;
use App\Enum\Musician\SkillLevel;
use App\Repository\Musician\MusicianProfileInstrumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: MusicianProfileInstrumentRepository::class)]
#[ORM\Table(name: 'user_musician_profile_instrument')]
#[ORM\UniqueConstraint(name: 'musician_profile_instrument_unique', columns: ['musician_profile_id', 'instrument_id'])]
class MusicianProfileInstrument
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: MusicianProfile::class, inversedBy: 'instruments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MusicianProfile $musicianProfile;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Instrument $instrument;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: SkillLevel::class)]
    private SkillLevel $skillLevel;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getMusicianProfile(): MusicianProfile
    {
        return $this->musicianProfile;
    }

    public function setMusicianProfile(MusicianProfile $musicianProfile): self
    {
        $this->musicianProfile = $musicianProfile;

        return $this;
    }

    public function getInstrument(): Instrument
    {
        return $this->instrument;
    }

    public function setInstrument(Instrument $instrument): self
    {
        $this->instrument = $instrument;

        return $this;
    }

    public function getSkillLevel(): SkillLevel
    {
        return $this->skillLevel;
    }

    public function setSkillLevel(SkillLevel $skillLevel): self
    {
        $this->skillLevel = $skillLevel;

        return $this;
    }
}

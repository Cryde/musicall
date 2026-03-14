<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\Attribute\Style;
use App\Entity\Metric\ViewCache;
use App\Entity\User;
use App\Repository\Teacher\TeacherProfileRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TeacherProfileRepository::class)]
#[ORM\Table(name: 'user_teacher_profile')]
class TeacherProfile implements ViewableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\OneToOne(inversedBy: 'teacherProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $yearsOfExperience = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $studentLevels = [];

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $ageGroups = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $courseTitle = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $offersTrial = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $trialPrice = null;

    /**
     * @var Collection<int, TeacherProfileInstrument>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfileInstrument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $instruments;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    #[ORM\JoinTable(name: 'user_teacher_profile_style')]
    public Collection $styles;

    /**
     * @var Collection<int, TeacherProfileMedia>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfileMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $media;

    /**
     * @var Collection<int, TeacherProfilePricing>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfilePricing::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $pricing;

    /**
     * @var Collection<int, TeacherAvailability>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherAvailability::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $availability;

    /**
     * @var Collection<int, TeacherProfileLocation>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfileLocation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $locations;

    /**
     * @var Collection<int, TeacherProfilePackage>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfilePackage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $packages;

    /**
     * @var Collection<int, TeacherSocialLink>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherSocialLink::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $socialLinks;

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
        $this->pricing = new ArrayCollection();
        $this->availability = new ArrayCollection();
        $this->locations = new ArrayCollection();
        $this->packages = new ArrayCollection();
        $this->socialLinks = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function addInstrument(TeacherProfileInstrument $instrument): self
    {
        if (!$this->instruments->contains($instrument)) {
            $this->instruments->add($instrument);
            $instrument->teacherProfile = $this;
        }

        return $this;
    }

    public function removeInstrument(TeacherProfileInstrument $instrument): self
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

    public function addMedia(TeacherProfileMedia $media): self
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->teacherProfile = $this;
        }

        return $this;
    }

    public function removeMedia(TeacherProfileMedia $media): self
    {
        $this->media->removeElement($media);

        return $this;
    }

    public function addPricing(TeacherProfilePricing $pricing): self
    {
        if (!$this->pricing->contains($pricing)) {
            $this->pricing->add($pricing);
            $pricing->teacherProfile = $this;
        }

        return $this;
    }

    public function removePricing(TeacherProfilePricing $pricing): self
    {
        $this->pricing->removeElement($pricing);

        return $this;
    }

    public function addAvailability(TeacherAvailability $availability): self
    {
        if (!$this->availability->contains($availability)) {
            $this->availability->add($availability);
            $availability->teacherProfile = $this;
        }

        return $this;
    }

    public function removeAvailability(TeacherAvailability $availability): self
    {
        $this->availability->removeElement($availability);

        return $this;
    }

    public function addLocation(TeacherProfileLocation $location): self
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
            $location->teacherProfile = $this;
        }

        return $this;
    }

    public function removeLocation(TeacherProfileLocation $location): self
    {
        $this->locations->removeElement($location);

        return $this;
    }

    public function addPackage(TeacherProfilePackage $package): self
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
            $package->teacherProfile = $this;
        }

        return $this;
    }

    public function removePackage(TeacherProfilePackage $package): self
    {
        $this->packages->removeElement($package);

        return $this;
    }

    public function addSocialLink(TeacherSocialLink $socialLink): self
    {
        if (!$this->socialLinks->contains($socialLink)) {
            $this->socialLinks->add($socialLink);
            $socialLink->teacherProfile = $this;
        }

        return $this;
    }

    public function removeSocialLink(TeacherSocialLink $socialLink): self
    {
        $this->socialLinks->removeElement($socialLink);

        return $this;
    }

    public function getViewableId(): ?string
    {
        /** @var string|null */
        return $this->id;
    }

    public function getViewableType(): string
    {
        return 'teacher_profile';
    }
}

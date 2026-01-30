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

#[ORM\Entity(repositoryClass: TeacherProfileRepository::class)]
#[ORM\Table(name: 'user_teacher_profile')]
class TeacherProfile implements ViewableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\OneToOne(inversedBy: 'teacherProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $yearsOfExperience = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $studentLevels = [];

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $ageGroups = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $courseTitle = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $offersTrial = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $trialPrice = null;

    /**
     * @var Collection<int, TeacherProfileInstrument>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfileInstrument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $instruments;

    /**
     * @var Collection<int, Style>
     */
    #[ORM\ManyToMany(targetEntity: Style::class)]
    #[ORM\JoinTable(name: 'user_teacher_profile_style')]
    private Collection $styles;

    /**
     * @var Collection<int, TeacherProfileMedia>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfileMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $media;

    /**
     * @var Collection<int, TeacherProfilePricing>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfilePricing::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $pricing;

    /**
     * @var Collection<int, TeacherAvailability>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherAvailability::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $availability;

    /**
     * @var Collection<int, TeacherProfileLocation>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfileLocation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $locations;

    /**
     * @var Collection<int, TeacherProfilePackage>
     */
    #[ORM\OneToMany(mappedBy: 'teacherProfile', targetEntity: TeacherProfilePackage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $packages;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updateDatetime = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    private ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->instruments = new ArrayCollection();
        $this->styles = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->pricing = new ArrayCollection();
        $this->availability = new ArrayCollection();
        $this->locations = new ArrayCollection();
        $this->packages = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getYearsOfExperience(): ?int
    {
        return $this->yearsOfExperience;
    }

    public function setYearsOfExperience(?int $yearsOfExperience): self
    {
        $this->yearsOfExperience = $yearsOfExperience;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getStudentLevels(): array
    {
        return $this->studentLevels;
    }

    /**
     * @param array<string> $studentLevels
     */
    public function setStudentLevels(array $studentLevels): self
    {
        $this->studentLevels = $studentLevels;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getAgeGroups(): array
    {
        return $this->ageGroups;
    }

    /**
     * @param array<string> $ageGroups
     */
    public function setAgeGroups(array $ageGroups): self
    {
        $this->ageGroups = $ageGroups;

        return $this;
    }

    public function getCourseTitle(): ?string
    {
        return $this->courseTitle;
    }

    public function setCourseTitle(?string $courseTitle): self
    {
        $this->courseTitle = $courseTitle;

        return $this;
    }

    public function offersTrial(): bool
    {
        return $this->offersTrial;
    }

    public function setOffersTrial(bool $offersTrial): self
    {
        $this->offersTrial = $offersTrial;

        return $this;
    }

    public function getTrialPrice(): ?int
    {
        return $this->trialPrice;
    }

    public function setTrialPrice(?int $trialPrice): self
    {
        $this->trialPrice = $trialPrice;

        return $this;
    }

    /**
     * @return Collection<int, TeacherProfileInstrument>
     */
    public function getInstruments(): Collection
    {
        return $this->instruments;
    }

    public function addInstrument(TeacherProfileInstrument $instrument): self
    {
        if (!$this->instruments->contains($instrument)) {
            $this->instruments->add($instrument);
            $instrument->setTeacherProfile($this);
        }

        return $this;
    }

    public function removeInstrument(TeacherProfileInstrument $instrument): self
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

    /**
     * @return Collection<int, TeacherProfileMedia>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(TeacherProfileMedia $media): self
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setTeacherProfile($this);
        }

        return $this;
    }

    public function removeMedia(TeacherProfileMedia $media): self
    {
        $this->media->removeElement($media);

        return $this;
    }

    /**
     * @return Collection<int, TeacherProfilePricing>
     */
    public function getPricing(): Collection
    {
        return $this->pricing;
    }

    public function addPricing(TeacherProfilePricing $pricing): self
    {
        if (!$this->pricing->contains($pricing)) {
            $this->pricing->add($pricing);
            $pricing->setTeacherProfile($this);
        }

        return $this;
    }

    public function removePricing(TeacherProfilePricing $pricing): self
    {
        $this->pricing->removeElement($pricing);

        return $this;
    }

    /**
     * @return Collection<int, TeacherAvailability>
     */
    public function getAvailability(): Collection
    {
        return $this->availability;
    }

    public function addAvailability(TeacherAvailability $availability): self
    {
        if (!$this->availability->contains($availability)) {
            $this->availability->add($availability);
            $availability->setTeacherProfile($this);
        }

        return $this;
    }

    public function removeAvailability(TeacherAvailability $availability): self
    {
        $this->availability->removeElement($availability);

        return $this;
    }

    /**
     * @return Collection<int, TeacherProfileLocation>
     */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    public function addLocation(TeacherProfileLocation $location): self
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
            $location->setTeacherProfile($this);
        }

        return $this;
    }

    public function removeLocation(TeacherProfileLocation $location): self
    {
        $this->locations->removeElement($location);

        return $this;
    }

    /**
     * @return Collection<int, TeacherProfilePackage>
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function addPackage(TeacherProfilePackage $package): self
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
            $package->setTeacherProfile($this);
        }

        return $this;
    }

    public function removePackage(TeacherProfilePackage $package): self
    {
        $this->packages->removeElement($package);

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

    public function getViewCache(): ?ViewCache
    {
        return $this->viewCache;
    }

    public function setViewCache(?ViewCache $viewCache): self
    {
        $this->viewCache = $viewCache;

        return $this;
    }

    public function getViewableId(): ?string
    {
        return $this->id;
    }

    public function getViewableType(): string
    {
        return 'teacher_profile';
    }
}

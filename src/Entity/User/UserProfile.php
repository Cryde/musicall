<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\Image\UserProfileCoverPicture;
use App\Entity\Metric\ViewCache;
use App\Entity\User;
use App\Repository\User\UserProfileRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
class UserProfile implements ViewableInterface
{
    final public const string ITEM = 'USER_PROFILE_ITEM';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\OneToOne(inversedBy: 'profile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isPublic = true;

    #[ORM\OneToOne(targetEntity: UserProfileCoverPicture::class, cascade: ['persist', 'remove'])]
    private ?UserProfileCoverPicture $coverPicture = null;

    /**
     * @var Collection<int, UserSocialLink>
     */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: UserSocialLink::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $socialLinks;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updateDatetime = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    private ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->socialLinks = new ArrayCollection();
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

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getCoverPicture(): ?UserProfileCoverPicture
    {
        return $this->coverPicture;
    }

    public function setCoverPicture(?UserProfileCoverPicture $coverPicture): self
    {
        $this->coverPicture = $coverPicture;

        return $this;
    }

    /**
     * @return Collection<int, UserSocialLink>
     */
    public function getSocialLinks(): Collection
    {
        return $this->socialLinks;
    }

    public function addSocialLink(UserSocialLink $socialLink): self
    {
        if (!$this->socialLinks->contains($socialLink)) {
            $this->socialLinks->add($socialLink);
            $socialLink->setProfile($this);
        }

        return $this;
    }

    public function removeSocialLink(UserSocialLink $socialLink): self
    {
        $this->socialLinks->removeElement($socialLink);

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
        return 'user_profile';
    }
}

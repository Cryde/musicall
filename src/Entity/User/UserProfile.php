<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\Image\UserProfileCoverPicture;
use App\Entity\Metric\ViewCache;
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
    public ?string $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $bio = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $location = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $displayName = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isPublic = true;

    #[ORM\OneToOne(targetEntity: UserProfileCoverPicture::class, cascade: ['persist', 'remove'])]
    public ?UserProfileCoverPicture $coverPicture = null;

    /**
     * @var Collection<int, UserSocialLink>
     */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: UserSocialLink::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $socialLinks;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $updateDatetime = null;

    #[ORM\OneToOne(targetEntity: ViewCache::class, cascade: ['persist', 'remove'])]
    public ?ViewCache $viewCache = null;

    public function __construct()
    {
        $this->socialLinks = new ArrayCollection();
        $this->creationDatetime = new DateTimeImmutable();
    }

    public function addSocialLink(UserSocialLink $socialLink): self
    {
        if (!$this->socialLinks->contains($socialLink)) {
            $this->socialLinks->add($socialLink);
            $socialLink->profile = $this;
        }

        return $this;
    }

    public function removeSocialLink(UserSocialLink $socialLink): self
    {
        $this->socialLinks->removeElement($socialLink);

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

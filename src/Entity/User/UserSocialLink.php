<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\Trait\SocialLinkTrait;
use App\Repository\User\UserSocialLinkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSocialLinkRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_profile_platform', columns: ['profile_id', 'platform'])]
class UserSocialLink
{
    use SocialLinkTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class, inversedBy: 'socialLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserProfile $profile;

    public function __construct()
    {
        $this->initializeSocialLinkTrait();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function setProfile(UserProfile $profile): self
    {
        $this->profile = $profile;

        return $this;
    }
}

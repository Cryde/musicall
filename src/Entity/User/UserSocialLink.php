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
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class, inversedBy: 'socialLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public UserProfile $profile;

    public function __construct()
    {
        $this->initializeSocialLinkTrait();
    }
}

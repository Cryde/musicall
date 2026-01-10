<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Enum\SocialPlatform;
use App\Repository\User\UserSocialLinkRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSocialLinkRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_profile_platform', columns: ['profile_id', 'platform'])]
class UserSocialLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserProfile::class, inversedBy: 'socialLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserProfile $profile;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: SocialPlatform::class)]
    private SocialPlatform $platform;

    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 500)]
    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $url;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
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

    public function getPlatform(): SocialPlatform
    {
        return $this->platform;
    }

    public function setPlatform(SocialPlatform $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

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
}

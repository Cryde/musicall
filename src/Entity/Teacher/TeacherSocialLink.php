<?php

declare(strict_types=1);

namespace App\Entity\Teacher;

use App\Entity\Trait\SocialLinkTrait;
use App\Repository\Teacher\TeacherSocialLinkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeacherSocialLinkRepository::class)]
#[ORM\Table(name: 'user_teacher_social_link')]
#[ORM\UniqueConstraint(name: 'unique_teacher_profile_platform', columns: ['teacher_profile_id', 'platform'])]
class TeacherSocialLink
{
    use SocialLinkTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TeacherProfile::class, inversedBy: 'socialLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TeacherProfile $teacherProfile;

    public function __construct()
    {
        $this->initializeSocialLinkTrait();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeacherProfile(): TeacherProfile
    {
        return $this->teacherProfile;
    }

    public function setTeacherProfile(TeacherProfile $teacherProfile): self
    {
        $this->teacherProfile = $teacherProfile;

        return $this;
    }
}

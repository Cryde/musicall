<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentLikeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A member's like on a wall post comment or reply (#1331): one per member and comment. */
#[ORM\Entity(repositoryClass: ProfilePostCommentLikeRepository::class)]
#[ORM\Table(name: 'profile_post_comment_like')]
#[ORM\UniqueConstraint(name: 'profile_post_comment_like_unique', columns: ['comment_id', 'user_id'])]
class ProfilePostCommentLike
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ProfilePostComment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ProfilePostComment $comment;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}

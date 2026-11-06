<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use App\Entity\User;
use App\Repository\Profile\ProfilePostLikeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A member's like on a wall post (#1316): one per member and post. */
#[ORM\Entity(repositoryClass: ProfilePostLikeRepository::class)]
#[ORM\Table(name: 'profile_post_like')]
#[ORM\UniqueConstraint(name: 'profile_post_like_unique', columns: ['post_id', 'user_id'])]
#[ORM\Index(name: 'profile_post_like_post_idx', columns: ['post_id', 'creation_datetime'])]
class ProfilePostLike
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ProfilePost::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ProfilePost $post;

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

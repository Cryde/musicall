<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/** A comment on a wall post (#1316), or a reply to one: one level only. */
#[ORM\Entity(repositoryClass: ProfilePostCommentRepository::class)]
#[ORM\Table(name: 'profile_post_comment')]
#[ORM\Index(name: 'profile_post_comment_post_idx', columns: ['post_id', 'creation_datetime'])]
#[ORM\Index(name: 'profile_post_comment_liked_idx', columns: ['post_id', 'like_count', 'creation_datetime', 'id'])]
class ProfilePostComment
{
    final public const int MAX_LENGTH = 1000;

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
    public User $author;

    /** Removing a comment takes its replies with it, in the database. */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?ProfilePostComment $parent = null;

    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    /**
     * Every like it holds (#1331), kept in step by each like and unlike, for the « Plus appréciés » sort. What a
     * viewer is shown leaves out the people they block or who block them, counted apart.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true, 'default' => 0])]
    public int $likeCount = 0;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}

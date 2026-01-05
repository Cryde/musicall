<?php

declare(strict_types=1);

namespace App\Entity\Forum;

use App\Contracts\SluggableEntityInterface;
use App\Entity\User;
use App\Repository\Forum\ForumTopicRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: ForumTopicRepository::class)]
class ForumTopic implements SluggableEntityInterface
{

    final const TYPE_TOPIC_DEFAULT = 0;
    final const TYPE_TOPIC_PINNED = 1;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;
    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Forum $forum;
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $slug;
    #[ORM\Column(type: Types::INTEGER)]
    private int $type = self::TYPE_TOPIC_DEFAULT;
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isLocked = false;
    #[ORM\ManyToOne(targetEntity: ForumPost::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ForumPost $lastPost = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $postNumber = 0;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    public function getForum(): ?Forum
    {
        return $this->forum;
    }

    public function setForum(?Forum $forum): self
    {
        $this->forum = $forum;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getIsLocked(): ?bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): self
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    public function getLastPost(): ?ForumPost
    {
        return $this->lastPost;
    }

    public function setLastPost(?ForumPost $lastPost): self
    {
        $this->lastPost = $lastPost;

        return $this;
    }

    public function getCreationDatetime(): DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getPostNumber(): ?int
    {
        return $this->postNumber;
    }

    public function setPostNumber(int $postNumber): self
    {
        $this->postNumber = $postNumber;

        return $this;
    }
}

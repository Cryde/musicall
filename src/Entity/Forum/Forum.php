<?php

declare(strict_types=1);

namespace App\Entity\Forum;

use App\Contracts\SluggableEntityInterface;
use App\Repository\Forum\ForumRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
class Forum implements SluggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $slug;
    #[ORM\Column(type: Types::TEXT)]
    private string $description;
    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'forums')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumCategory $forumCategory;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updateDatetime = null;
    #[ORM\Column(type: Types::INTEGER)]
    private int $position;
    #[ORM\Column(type: Types::INTEGER)]
    private int $topicNumber;
    #[ORM\Column(type: Types::INTEGER)]
    private int $postNumber;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
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

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getForumCategory(): ?ForumCategory
    {
        return $this->forumCategory;
    }

    public function setForumCategory(?ForumCategory $forumCategory): self
    {
        $this->forumCategory = $forumCategory;

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

    public function getUpdateDatetime(): ?DateTimeInterface
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(?DateTimeInterface $updateDatetime): self
    {
        $this->updateDatetime = $updateDatetime;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getTopicNumber(): ?int
    {
        return $this->topicNumber;
    }

    public function setTopicNumber(int $topicNumber): self
    {
        $this->topicNumber = $topicNumber;

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

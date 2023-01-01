<?php

namespace App\Entity\Forum;

use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Contracts\SluggableEntityInterface;
use App\Repository\Forum\ForumRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ApiResource(operations: [
    new Get(normalizationContext: ['groups' => [Forum::ITEM]], name: 'api_forums_get_item'),
])]
class Forum implements SluggableEntityInterface
{
    final const ITEM = 'FORUM_ITEM';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([ForumCategory::LIST, Forum::ITEM, ForumTopic::ITEM])]
    #[ApiProperty(identifier: false)]
    private $id;
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups([ForumCategory::LIST, Forum::ITEM, ForumTopic::ITEM])]
    private string $title;
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups([ForumCategory::LIST, ForumTopic::ITEM])]
    #[ApiProperty(identifier: true)]
    private string $slug;
    #[ORM\Column(type: 'text')]
    #[Groups([ForumCategory::LIST])]
    private string $description;
    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'forums')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Forum::ITEM])]
    private ForumCategory $forumCategory;
    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $creationDatetime;
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $updateDatetime = null;
    #[ORM\Column(type: 'integer')]
    private int $position;
    #[ORM\Column(type: 'integer')]
    private int $topicNumber;
    #[ORM\Column(type: 'integer')]
    private int $postNumber;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function setSlug(string $slug)
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

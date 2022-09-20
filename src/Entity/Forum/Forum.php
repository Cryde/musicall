<?php

namespace App\Entity\Forum;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\Forum\ForumRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ApiResource(
    collectionOperations: [],
    itemOperations: ['get' => ['normalization_context' => ['groups' => [Forum::ITEM]]]]
)]
class Forum
{
    final const ITEM = 'FORUM_ITEM';
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'UUID')]
    #[Groups([ForumCategory::LIST, Forum::ITEM, ForumTopic::ITEM])]
    #[ApiProperty(identifier: false)]
    private $id;
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups([ForumCategory::LIST, Forum::ITEM, ForumTopic::ITEM])]
    private $title;
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups([ForumCategory::LIST, ForumTopic::ITEM])]
    #[ApiProperty(identifier: true)]
    private $slug;
    #[ORM\Column(type: 'text')]
    #[Groups([ForumCategory::LIST])]
    private $description;
    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'forums')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Forum::ITEM])]
    private $forumCategory;
    #[ORM\Column(type: 'datetime')]
    private $creationDatetime;
    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updateDatetime;
    #[ORM\Column(type: 'integer')]
    private $position;
    #[ORM\Column(type: 'integer')]
    private $topicNumber;
    #[ORM\Column(type: 'integer')]
    private $postNumber;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
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

    public function getCreationDatetime(): \DateTime
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getUpdateDatetime(): ?\DateTimeInterface
    {
        return $this->updateDatetime;
    }

    public function setUpdateDatetime(?\DateTimeInterface $updateDatetime): self
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

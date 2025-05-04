<?php

namespace App\Entity\Forum;

use ApiPlatform\OpenApi\Model\Operation;
use Doctrine\DBAL\Types\Types;
use DateTimeInterface;
use DateTime;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Contracts\SluggableEntityInterface;
use App\Entity\User;
use App\Repository\Forum\ForumTopicRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ForumTopicRepository::class)]
#[ApiResource(operations: [
    new Get(
        openapi: new Operation(tags: ['Forum']),
        normalizationContext: ['groups' => [ForumTopic::ITEM]],
        name: 'api_forum_topics_get_item',
    ),
    new GetCollection(
        openapi: new Operation(tags: ['Forum']),
        normalizationContext: ['groups' => [ForumTopic::LIST]],
        name: 'api_forum_topics_get_collection'
    )
],
    paginationClientEnabled: true,
    paginationItemsPerPage: 15
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['forum' => SearchFilterInterface::STRATEGY_EXACT])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['creationDatetime' => 'DESC'])]
class ForumTopic implements SluggableEntityInterface
{
    final const LIST = 'FORUM_TOPIC_LIST';
    final const ITEM = 'FORUM_TOPIC_ITEM';

    final const TYPE_TOPIC_DEFAULT = 0;
    final const TYPE_TOPIC_PINNED = 1;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([ForumTopic::LIST, ForumTopic::ITEM])]
    #[ApiProperty(identifier: false)]
    private $id;
    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([ForumTopic::ITEM])]
    private Forum $forum;
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([ForumTopic::LIST, ForumTopic::ITEM])]
    private string $title;
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups([ForumTopic::LIST, ForumTopic::ITEM])]
    private string $slug;
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups([ForumTopic::LIST])]
    private int $type = self::TYPE_TOPIC_DEFAULT;
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups([ForumTopic::LIST])]
    private bool $isLocked = false;
    #[ORM\ManyToOne(targetEntity: ForumPost::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([ForumTopic::LIST])]
    private ?ForumPost $lastPost = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([ForumTopic::LIST])]
    private DateTimeInterface $creationDatetime;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([ForumTopic::LIST])]
    private User $author;
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups([ForumTopic::LIST])]
    private int $postNumber = 0;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

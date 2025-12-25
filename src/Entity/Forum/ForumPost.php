<?php

namespace App\Entity\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\OpenApi\Model\Operation;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\User;
use App\Repository\Forum\ForumPostRepository;
use App\State\Processor\Forum\ForumPostPostProcessor;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ApiResource(operations: [
    new Get(
        openapi: new Operation(tags: ['Forum']),
        normalizationContext: ['groups' => [ForumPost::ITEM]],
    ),
    new GetCollection(
        openapi: new Operation(tags: ['Forum']),
        normalizationContext: ['groups' => [ForumPost::LIST]],
        name: 'api_forum_posts_get_collection',
    ),
    new Post(
        openapi: new Operation(tags: ['Forum']),
        normalizationContext: ['groups' => [ForumPost::ITEM], 'skip_null_values' => false],
        denormalizationContext: ['groups' => [ForumPost::POST]],
        security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
        name: 'api_forum_posts_post',
        processor: ForumPostPostProcessor::class
    )
], paginationItemsPerPage: 10
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['topic' => SearchFilterInterface::STRATEGY_EXACT])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['creationDatetime' => OrderFilterInterface::DIRECTION_ASC])]
class ForumPost
{
    final const LIST = 'FORUM_POST_LIST';
    final const ITEM = 'FORUM_POST_ITEM';
    final const POST = 'FORUM_POST_POST';

    final const MIN_MESSAGE_LENGTH = 10;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([ForumPost::LIST, ForumTopic::LIST, ForumPost::ITEM])]
    private $id;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([ForumPost::LIST, ForumTopic::LIST, ForumPost::ITEM])]
    private DateTimeInterface $creationDatetime;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([ForumPost::LIST])]
    private ?DateTimeInterface $updateDatetime = null;
    #[Assert\NotBlank]
    #[Assert\Length(min: ForumPost::MIN_MESSAGE_LENGTH)]
    #[ORM\Column(type: Types::TEXT)]
    #[Groups([ForumPost::LIST, ForumPost::POST, ForumPost::ITEM])]
    private string $content;
    #[Assert\NotBlank]
    #[ORM\ManyToOne(targetEntity: ForumTopic::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([ForumPost::POST])]
    private ForumTopic $topic;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([ForumPost::LIST, ForumTopic::LIST, ForumPost::ITEM])]
    #[ApiProperty(genId: false)]
    private User $creator;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getTopic(): ?ForumTopic
    {
        return $this->topic;
    }

    public function setTopic(?ForumTopic $topic): self
    {
        $this->topic = $topic;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): self
    {
        $this->creator = $creator;

        return $this;
    }
}

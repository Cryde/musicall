<?php

namespace App\Entity\Forum;

use DateTimeInterface;
use DateTime;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\User;
use App\Repository\Forum\ForumPostRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ApiResource(operations: [
    new Get(normalizationContext: ['groups' => [ForumPost::ITEM]]),
    new GetCollection(normalizationContext: ['groups' => [ForumPost::LIST]], name: 'api_forum_posts_get_collection')
], paginationItemsPerPage: 10
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['topic' => SearchFilterInterface::STRATEGY_EXACT])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['creationDatetime' => OrderFilterInterface::DIRECTION_ASC])]
class ForumPost
{
    final const LIST = 'FORUM_POST_LIST';
    final const ITEM = 'FORUM_POST_ITEM';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([ForumPost::LIST, ForumTopic::LIST])]
    private $id;
    #[ORM\Column(type: 'datetime')]
    #[Groups([ForumPost::LIST, ForumTopic::LIST])]
    private DateTimeInterface $creationDatetime;
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups([ForumPost::LIST])]
    private ?DateTimeInterface $updateDatetime = null;
    #[ORM\Column(type: 'text')]
    #[Groups([ForumPost::LIST])]
    private string $content;
    #[ORM\ManyToOne(targetEntity: ForumTopic::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ForumTopic $topic;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([ForumPost::LIST, ForumTopic::LIST])]
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

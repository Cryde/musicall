<?php

namespace App\Entity\Forum;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\User;
use App\Repository\Forum\ForumPostRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ApiResource(
    collectionOperations: [
        'get' => ['normalization_context' => ['groups' => [ForumPost::LIST]]],
    ],
    itemOperations: [
        'get'  => ['normalization_context' => ['groups' => [ForumPost::ITEM]]]
    ],
    paginationItemsPerPage: 10
)]
#[ApiFilter(SearchFilter::class, properties: ['topic' => SearchFilterInterface::STRATEGY_EXACT])]
#[ApiFilter(OrderFilter::class, properties: ['creationDatetime' => OrderFilterInterface::DIRECTION_ASC])]
class ForumPost
{
    final const LIST = 'FORUM_POST_LIST';
    final const ITEM = 'FORUM_POST_ITEM';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'UUID')]
    #[Groups([ForumPost::LIST, ForumTopic::LIST])]
    private $id;
    #[ORM\Column(type: 'datetime')]
    #[Groups([ForumPost::LIST, ForumTopic::LIST])]
    private $creationDatetime;
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups([ForumPost::LIST])]
    private $updateDatetime;
    #[ORM\Column(type: 'text')]
    #[Groups([ForumPost::LIST])]
    private $content;
    #[ORM\ManyToOne(targetEntity: ForumTopic::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $topic;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([ForumPost::LIST, ForumTopic::LIST])]
    private $creator;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

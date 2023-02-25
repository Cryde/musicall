<?php

namespace App\Entity\Message;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Provider\Message\MessageCollectionProvider;
use DateTimeInterface;
use DateTime;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/messages/{threadId}',
            uriVariables: ['threadId' => new Link(toProperty: 'thread', fromClass: MessageThread::class)],
            normalizationContext: ['groups' => [Message::LIST]],
            name: 'api_message_get_collection',
            provider: MessageCollectionProvider::class
        ),
    ]
)]
#[ApiFilter(OrderFilter::class, properties: ['creationDatetime' => OrderFilterInterface::DIRECTION_DESC])]
class Message
{
    const LIST = 'LIST_MESSAGE';
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([MessageThreadMeta::LIST, Message::LIST])]
    private DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MessageThreadMeta::LIST, Message::LIST])]
    private User $author;

    #[ORM\ManyToOne(targetEntity: MessageThread::class, inversedBy: "messages")]
    #[ORM\JoinColumn(nullable: false)]
    private MessageThread $thread;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups([MessageThreadMeta::LIST, Message::LIST])]
    private string $content;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreationDatetime(): ?DateTimeInterface
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

    public function getThread(): ?MessageThread
    {
        return $this->thread;
    }

    public function setThread(?MessageThread $thread): self
    {
        $this->thread = $thread;

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
}

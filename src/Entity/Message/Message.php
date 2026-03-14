<?php

declare(strict_types=1);

namespace App\Entity\Message;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\State\Processor\Message\MessagePostProcessor;
use App\State\Provider\Message\MessageCollectionProvider;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use App\Validator\Message\NotDeletedThreadRecipient;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[NotDeletedThreadRecipient]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/messages/{threadId}',
            uriVariables: ['threadId' => new Link(toProperty: 'thread', fromClass: MessageThread::class)],
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [Message::LIST]],
            name: 'api_message_get_collection',
            provider: MessageCollectionProvider::class
        ),
        new Post(
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [Message::ITEM]],
            denormalizationContext: ['groups' => [Message::POST]],
            name: 'api_message_post',
            processor: MessagePostProcessor::class
        ),
    ]
)]
#[ApiFilter(OrderFilter::class, properties: ['creationDatetime' => OrderFilterInterface::DIRECTION_DESC])]
class Message
{
    const LIST = 'LIST_MESSAGE';
    const ITEM = 'ITEM_MESSAGE';
    const POST = 'POST_MESSAGE';
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([Message::ITEM])]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([MessageThreadMeta::LIST, Message::LIST, Message::ITEM])]
    public DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MessageThreadMeta::LIST, Message::LIST, Message::ITEM])]
    public User $author;

    #[Assert\NotBlank]
    #[ORM\ManyToOne(targetEntity: MessageThread::class, inversedBy: "messages")]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([Message::POST, Message::ITEM])]
    public MessageThread $thread;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    #[Groups([MessageThreadMeta::LIST, Message::LIST, Message::ITEM, Message::POST])]
    public string $content;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

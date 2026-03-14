<?php

declare(strict_types=1);

namespace App\Entity\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use App\State\Processor\Message\MessageThreadMetaPatchProcessor;
use App\State\Provider\Message\MessageThreadMetaCollectionProvider;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MessageThreadMetaRepository::class)]
#[ORM\UniqueConstraint(name: 'message_thread_meta_unique', columns: ['thread_id', 'user_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMeta::LIST]],
            name: 'api_message_thread_meta_get_collection',
            provider: MessageThreadMetaCollectionProvider::class
        ),
        new Patch(
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMeta::ITEM]],
            denormalizationContext: ['groups' => [MessageThreadMeta::PATCH]],
            name: 'api_message_thread_meta_patch',
            processor: MessageThreadMetaPatchProcessor::class
        ),
    ]
)]
class MessageThreadMeta
{
    const PATCH = 'MESSAGE_THREAT_PATCH';
    const LIST = 'MESSAGE_THREAT_META_LIST';
    const ITEM = 'MESSAGE_THREAT_META_ITEM';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([MessageThreadMeta::LIST, MessageThreadMeta::ITEM])]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: MessageThread::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MessageThreadMeta::LIST])]
    public MessageThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups([MessageThreadMeta::LIST, MessageThreadMeta::PATCH])]
    public bool $isRead;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isDeleted;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

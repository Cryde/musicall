<?php

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
    private $id;

    #[ORM\ManyToOne(targetEntity: MessageThread::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MessageThreadMeta::LIST])]
    private MessageThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups([MessageThreadMeta::LIST, MessageThreadMeta::PATCH])]
    private bool $isRead;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDeleted;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getIsRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Repository\Message\MessageThreadRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MessageThreadRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThread::ITEM]],
        )
    ]
)]
class MessageThread
{
    const ITEM = 'MESSAGE_THREAD_ITEM';
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([MessageThreadMeta::LIST, Message::ITEM])]
    private ?UuidInterface $id = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: Message::class)]
    private Collection $messages;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $creationDatetime;
    /**
     * @var Collection<int, MessageParticipant>
     */
    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: MessageParticipant::class)]
    #[Groups([MessageThreadMeta::LIST])]
    private Collection $messageParticipants;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([MessageThreadMeta::LIST])]
    private ?Message $lastMessage = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->messages = new ArrayCollection();
        $this->messageParticipants = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id?->toString();
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setThread($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        $this->messages->removeElement($message);

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

    /**
     * @return Collection<int, MessageParticipant>
     */
    public function getMessageParticipants(): Collection
    {
        return $this->messageParticipants;
    }

    public function addMessageParticipant(MessageParticipant $messageParticipant): self
    {
        if (!$this->messageParticipants->contains($messageParticipant)) {
            $this->messageParticipants[] = $messageParticipant;
            $messageParticipant->setThread($this);
        }

        return $this;
    }

    public function removeMessageParticipant(MessageParticipant $messageParticipant): self
    {
        $this->messageParticipants->removeElement($messageParticipant);

        return $this;
    }

    public function getLastMessage(): ?Message
    {
        return $this->lastMessage;
    }

    public function setLastMessage(?Message $lastMessage): self
    {
        $this->lastMessage = $lastMessage;

        return $this;
    }
}

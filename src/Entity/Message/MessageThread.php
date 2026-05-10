<?php

declare(strict_types=1);

namespace App\Entity\Message;

use ApiPlatform\Metadata\ApiResource;
use App\ApiResource\Message\MessageResource;
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

/**
 * `#[ApiResource(operations: [])]` keeps the entity registered so that incoming
 * `/api/message_threads/{id}` IRIs can be resolved into entity instances by the
 * Symfony serializer (used by `MessageResource->thread` denormalization on POST
 * /messages). It also lets the entity render nested in POST responses with a
 * proper @id/@type. No HTTP routes are exposed.
 */
#[ORM\Entity(repositoryClass: MessageThreadRepository::class)]
#[ApiResource(operations: [])]
class MessageThread
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([MessageResource::ITEM])]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'thread')]
    public Collection $messages;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    /**
     * @var Collection<int, MessageParticipant>
     */
    #[ORM\OneToMany(targetEntity: MessageParticipant::class, mappedBy: 'thread')]
    public Collection $messageParticipants;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Message $lastMessage = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->messages = new ArrayCollection();
        $this->messageParticipants = new ArrayCollection();
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->thread = $this;
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        $this->messages->removeElement($message);

        return $this;
    }

    public function addMessageParticipant(MessageParticipant $messageParticipant): self
    {
        if (!$this->messageParticipants->contains($messageParticipant)) {
            $this->messageParticipants[] = $messageParticipant;
            $messageParticipant->thread = $this;
        }

        return $this;
    }

    public function removeMessageParticipant(MessageParticipant $messageParticipant): self
    {
        $this->messageParticipants->removeElement($messageParticipant);

        return $this;
    }
}

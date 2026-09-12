<?php

declare(strict_types=1);

namespace App\Entity\Message;

use App\Entity\BandSpace\BandSpace;
use App\Repository\Message\MessageThreadRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: MessageThreadRepository::class)]
#[ORM\Table(name: 'message_thread')]
#[ORM\UniqueConstraint(name: 'message_thread_band_space_name_unique', columns: ['band_space_id', 'name'])]
class MessageThread
{
    public const string DEFAULT_CHANNEL_NAME = 'Général';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
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

    /**
     * Null is a direct message, whose participants are the rows above. Set is a Band Space channel,
     * whose members are derived from BandSpaceMembership so that nothing has to be synchronised on
     * join, leave, kick or role change.
     */
    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?BandSpace $bandSpace = null;

    /** Channels only, and never null for one: two nulls read as distinct, so the unique index cannot say it. */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $name = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->messages = new ArrayCollection();
        $this->messageParticipants = new ArrayCollection();
    }

    public function isChannel(): bool
    {
        return $this->bandSpace instanceof BandSpace;
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

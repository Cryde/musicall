<?php

namespace App\Entity\Message;

use App\Repository\Message\MessageThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageThreadRepository::class)]
class MessageThread
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::GUID)]
    #[ORM\GeneratedValue(strategy: 'UUID')]
    private $id;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'thread')]
    private $messages;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $creationDatetime;

    #[ORM\OneToMany(targetEntity: MessageParticipant::class, mappedBy: 'thread')]
    private $messageParticipants;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    private $lastMessage;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
        $this->messages = new ArrayCollection();
        $this->messageParticipants = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return Collection|Message[]
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
        if ($this->messages->contains($message)) {
            $this->messages->removeElement($message);
            // set the owning side to null (unless already changed)
            if ($message->getThread() === $this) {
                $message->setThread(null);
            }
        }

        return $this;
    }

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(?\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    /**
     * @return Collection|MessageParticipant[]
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
        if ($this->messageParticipants->contains($messageParticipant)) {
            $this->messageParticipants->removeElement($messageParticipant);
            // set the owning side to null (unless already changed)
            if ($messageParticipant->getThread() === $this) {
                $messageParticipant->setThread(null);
            }
        }

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

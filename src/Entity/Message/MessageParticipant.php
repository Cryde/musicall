<?php

namespace App\Entity\Message;

use App\Entity\User;
use App\Repository\Message\MessageParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MessageParticipantRepository::class)
 * @ORM\Table(
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="message_participant_unique",
 *            columns={"thread_id", "participant_id"}
 *        )
 *    }
 * )
 */
class MessageParticipant
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=MessageThread::class, inversedBy="messageParticipants")
     * @ORM\JoinColumn(nullable=false)
     */
    private $thread;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $participant;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?int
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

    public function getParticipant(): ?User
    {
        return $this->participant;
    }

    public function setParticipant(?User $participant): self
    {
        $this->participant = $participant;

        return $this;
    }

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }
}

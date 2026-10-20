<?php

declare(strict_types=1);

namespace App\Entity\Message;

use DateTimeInterface;
use DateTime;
use App\Entity\User;
use App\Repository\Message\MessageParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity(repositoryClass: MessageParticipantRepository::class)]
#[ORM\UniqueConstraint(name: 'message_participant_unique', columns: ['thread_id', 'participant_id'])]
class MessageParticipant
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: MessageThread::class, inversedBy: "messageParticipants")]
    #[ORM\JoinColumn(nullable: false)]
    public MessageThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $participant;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

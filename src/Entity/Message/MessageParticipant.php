<?php

declare(strict_types=1);

namespace App\Entity\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use DateTimeInterface;
use DateTime;
use App\Entity\User;
use App\Repository\Message\MessageParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MessageParticipantRepository::class)]
#[ORM\UniqueConstraint(name: 'message_participant_unique', columns: ['thread_id', 'participant_id'])]
#[ApiResource(
    operations: [
        new Get(
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageParticipant::ITEM]],
        )
    ]
)]
class MessageParticipant
{
    const ITEM = 'MESSAGE_PARTICIPANT_ITEM';
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: MessageThread::class, inversedBy: "messageParticipants")]
    #[ORM\JoinColumn(nullable: false)]
    public MessageThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([MessageThreadMeta::LIST])]
    public User $participant;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Message;

use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: MessageThreadMetaRepository::class)]
#[ORM\UniqueConstraint(name: 'message_thread_meta_unique', columns: ['thread_id', 'user_id'])]
class MessageThreadMeta
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: MessageThread::class)]
    #[ORM\JoinColumn(nullable: false)]
    public MessageThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isRead;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isDeleted;

    /**
     * One email per unread streak (#533): set true when the message-received
     * email is sent, reset to false when the recipient marks the thread as
     * read. While true, subsequent messages in the same thread do not
     * re-trigger an email.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $pendingNotificationSent = false;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}

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

    /**
     * How far this user has read in the thread, or null when they have read nothing. An unread count
     * is then the messages after it that somebody else wrote.
     *
     * A position rather than the boolean it replaced (#954), because a boolean can say "something is
     * new" and cannot say how much or where. A datetime rather than a relation to the last read
     * message, because Message ids are uuid4 and therefore carry no order: a relation would have had
     * to fall back on this very column to compare two messages, so it would have bought a foreign key
     * and no accuracy.
     *
     * The column is one-second precision, like message.creation_datetime. A message written in the
     * same second as a mark-read, just after it, therefore reads as already read and stays hidden
     * until the next one arrives. It has one knock-on worth knowing: the client only marks a thread
     * read when the count is non-zero, and that reset is what clears pendingNotificationSent, so such
     * a message also suppresses the *next* email. It heals on the following read, so the worst case
     * is one late message and one missing email. Making it exact means microsecond precision on
     * message.creation_datetime, not a different shape here.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $lastReadDatetime = null;

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

    /**
     * Whether the thread has moved on since this user last read it.
     *
     * This is what the `isRead` boolean used to answer directly, and it is the same predicate the
     * #954 migration uses in both directions. Compared against the thread's denormalised last message
     * rather than counted, because the only caller is the email throttle, which wants a yes or no and
     * not a number. That costs no query for its current caller, because the PATCH provider has already
     * built the thread resource and warmed the association; reached cold it would be two lazy loads.
     *
     * A null position is unread even in a thread with no messages, which is exactly what a `false`
     * boolean meant, and is the safe direction for the flag it guards.
     *
     * Unlike the unread *count*, this ignores who wrote the last message, so the two can disagree: a
     * sender whose own message landed a second after their position stamp reads as having unread
     * while their count is zero. Harmless where it is used, but do not read this as `count > 0`.
     */
    public function hasUnread(): bool
    {
        if ($this->lastReadDatetime === null) {
            return true;
        }

        if ($this->thread->lastMessage === null) {
            return false;
        }

        return $this->lastReadDatetime < $this->thread->lastMessage->creationDatetime;
    }
}

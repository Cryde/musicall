<?php

declare(strict_types=1);

namespace App\Entity\Message;

use App\Entity\User;
use App\Repository\Message\MessageMentionRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * Who a chat message named with an `@` (#964).
 *
 * The content keeps the `@[uuid]` token, which is what says *where* in the sentence the name goes, so
 * this is not a second copy of that. What it adds is the fact itself: who was named is a row rather
 * than something every reader re-derives with a regex, which is what lets the renderer resolve a
 * member who has since left the band. Resolving against the active roster instead would print
 * `@inconnu` over their name in history, and a chat log is exactly where that matters.
 *
 * `@[tous]` is expanded here, one row per active member at the time of sending, so "messages that
 * mention me" stays a single predicate whichever way the sender wrote it.
 *
 * No `creationDatetime`: the message carries it, and a mention cannot outlive its message.
 */
#[ORM\Entity(repositoryClass: MessageMentionRepository::class)]
#[ORM\Table(name: 'message_mention')]
#[ORM\UniqueConstraint(name: 'message_mention_unique', columns: ['message_id', 'mentioned_user_id'])]
#[ORM\Index(name: 'idx_message_mention_user', columns: ['mentioned_user_id'])]
class MessageMention
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Message $message;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'mentioned_user_id', nullable: false)]
    public User $mentionedUser;

    public function __construct(Message $message, User $mentionedUser)
    {
        $this->message = $message;
        $this->mentionedUser = $mentionedUser;
    }
}

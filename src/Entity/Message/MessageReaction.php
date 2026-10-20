<?php

declare(strict_types=1);

namespace App\Entity\Message;

use App\Enum\Message\MessageReactionEmoji;
use App\Entity\User;
use App\Repository\Message\MessageReactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * One member's one reaction to one chat message (#968).
 *
 * The emoji is stored as the enum slug, so the column stays plain ascii and a re-skin never needs a
 * data migration. See MessageReactionEmoji.
 *
 * No creationDatetime: nothing shows when a reaction was left, and the aggregate is ordered by the
 * enum rather than by time. Same reasoning as MessageMention.
 *
 * A member who leaves keeps their reactions, exactly as they keep their messages: the count on an
 * old message is a record of what the band thought at the time, and rewriting it when somebody walks
 * away would falsify history.
 */
#[ORM\Entity(repositoryClass: MessageReactionRepository::class)]
#[ORM\Table(name: 'message_reaction')]
#[ORM\UniqueConstraint(name: 'message_reaction_unique', columns: ['message_id', 'user_id', 'emoji'])]
#[ORM\Index(name: 'idx_message_reaction_user', columns: ['user_id'])]
class MessageReaction
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Message $message;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MessageReactionEmoji::class)]
    public MessageReactionEmoji $emoji;

    public function __construct(Message $message, User $user, MessageReactionEmoji $emoji)
    {
        $this->message = $message;
        $this->user = $user;
        $this->emoji = $emoji;
    }
}

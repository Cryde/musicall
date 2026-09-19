<?php

declare(strict_types=1);

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageReaction;
use App\Enum\Message\MessageReactionEmoji;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MessageReaction>
 */
final class MessageReactionFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'message' => MessageFactory::new(),
            'user' => UserFactory::new(),
            'emoji' => MessageReactionEmoji::ThumbsUp,
        ];
    }

    public static function class(): string
    {
        return MessageReaction::class;
    }
}

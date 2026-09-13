<?php

declare(strict_types=1);

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageMention;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MessageMention>
 */
final class MessageMentionFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'message' => MessageFactory::new(),
            'mentionedUser' => UserFactory::new(),
        ];
    }

    public static function class(): string
    {
        return MessageMention::class;
    }
}

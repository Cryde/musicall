<?php

declare(strict_types=1);

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageAttachment;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use Ramsey\Uuid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MessageAttachment>
 */
final class MessageAttachmentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'message' => MessageFactory::new(),
            'targetType' => BandSpaceSearchResultType::Task,
            // A target that resolves is the caller's business: the default points at nothing, which is
            // the orphan case the label snapshot exists for.
            'targetId' => Uuid::uuid4(),
            'label' => self::faker()->words(3, true),
        ];
    }

    public static function class(): string
    {
        return MessageAttachment::class;
    }
}

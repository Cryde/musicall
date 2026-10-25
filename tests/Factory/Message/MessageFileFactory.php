<?php

declare(strict_types=1);

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageFile;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MessageFile>
 */
final class MessageFileFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'thread' => MessageThreadFactory::new(),
            'uploadedBy' => UserFactory::new(),
            'mimeType' => 'image/webp',
            'originalName' => 'photo.webp',
        ];
    }

    public static function class(): string
    {
        return MessageFile::class;
    }
}

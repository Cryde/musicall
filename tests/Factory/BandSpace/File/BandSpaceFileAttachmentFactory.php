<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BandSpaceFileAttachment>
 */
final class BandSpaceFileAttachmentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpaceFile' => BandSpaceFileFactory::new(),
            'sourceType' => 'task',
            'sourceId' => Uuid::uuid4(),
            'attachedBy' => UserFactory::new(),
            'attachedDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceFileAttachment::class;
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BandSpaceFileVersion>
 */
final class BandSpaceFileVersionFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpaceFile' => BandSpaceFileFactory::new(),
            'versionNumber' => 1,
            'createdBy' => UserFactory::new(),
            'mimeType' => 'application/pdf',
            'size' => self::faker()->numberBetween(1024, 10_000_000),
            'storagePath' => self::faker()->uuid() . '.pdf',
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceFileVersion::class;
    }
}

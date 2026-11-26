<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace\File;

use App\Entity\BandSpace\SongFileFolder;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SongFileFolder>
 */
final class SongFileFolderFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'song' => SongFactory::new(),
            'createdBy' => UserFactory::new(),
            'name' => self::faker()->unique()->word(),
            'creationDatetime' => new \DateTime('2026-11-20 10:00:00'),
        ];
    }

    public static function class(): string
    {
        return SongFileFolder::class;
    }
}

<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Announce;

use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Factory;
use App\Entity\Musician\MusicianAnnounce;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<MusicianAnnounce>
 */
final class MusicianAnnounceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTimeBetween('-1 year'),
            'instrument' => InstrumentFactory::new(),
            // Mostly French, as on the site, with a few others for the search's language filter (#1198).
            'languages' => self::faker()->randomElement([['fr'], ['fr'], ['fr'], ['fr', 'en'], ['en'], ['fr', 'nl'], ['nl', 'de', 'en']]),
            'latitude' => self::faker()->latitude(),
            'locationName' => self::faker()->city(),
            'longitude' => self::faker()->longitude(),
            'note' => self::faker()->sentence(),
            'type' => self::faker()->randomElement([MusicianAnnounce::TYPE_BAND, MusicianAnnounce::TYPE_MUSICIAN]),
        ];
    }

    public static function class(): string
    {
        return MusicianAnnounce::class;
    }
}

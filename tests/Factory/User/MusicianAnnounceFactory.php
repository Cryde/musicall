<?php

namespace App\Tests\Factory\User;

use App\Entity\Musician\MusicianAnnounce;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<MusicianAnnounce>
 *
 * @method        MusicianAnnounce|Proxy create(array|callable $attributes = [])
 * @method static MusicianAnnounce|Proxy createOne(array $attributes = [])
 * @method static MusicianAnnounce|Proxy find(object|array|mixed $criteria)
 * @method static MusicianAnnounce|Proxy findOrCreate(array $attributes)
 * @method static MusicianAnnounce|Proxy first(string $sortedField = 'id')
 * @method static MusicianAnnounce|Proxy last(string $sortedField = 'id')
 * @method static MusicianAnnounce|Proxy random(array $attributes = [])
 * @method static MusicianAnnounce|Proxy randomOrCreate(array $attributes = [])
 * @method static MusicianAnnounceRepository|RepositoryProxy repository()
 * @method static MusicianAnnounce[]|Proxy[] all()
 * @method static MusicianAnnounce[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MusicianAnnounce[]|Proxy[] createSequence(array|callable $sequence)
 * @method static MusicianAnnounce[]|Proxy[] findBy(array $attributes)
 * @method static MusicianAnnounce[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MusicianAnnounce[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<MusicianAnnounce> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<MusicianAnnounce> createOne(array $attributes = [])
 * @phpstan-method static Proxy<MusicianAnnounce> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<MusicianAnnounce> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<MusicianAnnounce> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<MusicianAnnounce> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<MusicianAnnounce> random(array $attributes = [])
 * @phpstan-method static Proxy<MusicianAnnounce> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<MusicianAnnounceRepository> repository()
 * @phpstan-method static list<Proxy<MusicianAnnounce>> all()
 * @phpstan-method static list<Proxy<MusicianAnnounce>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<MusicianAnnounce>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<MusicianAnnounce>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<MusicianAnnounce>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<MusicianAnnounce>> randomSet(int $number, array $attributes = [])
 */
final class MusicianAnnounceFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
            'instrument' => InstrumentFactory::new(),
            'latitude' => self::faker()->text(255),
            'locationName' => self::faker()->text(255),
            'longitude' => self::faker()->text(255),
            'note' => self::faker()->text(),
            'type' => self::faker()->numberBetween(1, 32767),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(MusicianAnnounce $musicianAnnounce): void {})
        ;
    }

    protected static function getClass(): string
    {
        return MusicianAnnounce::class;
    }
}

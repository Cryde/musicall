<?php

namespace App\Tests\Factory\Wiki;

use App\Entity\Wiki\Artist;
use App\Repository\Wiki\ArtistRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Artist>
 *
 * @method        Artist|Proxy create(array|callable $attributes = [])
 * @method static Artist|Proxy createOne(array $attributes = [])
 * @method static Artist|Proxy find(object|array|mixed $criteria)
 * @method static Artist|Proxy findOrCreate(array $attributes)
 * @method static Artist|Proxy first(string $sortedField = 'id')
 * @method static Artist|Proxy last(string $sortedField = 'id')
 * @method static Artist|Proxy random(array $attributes = [])
 * @method static Artist|Proxy randomOrCreate(array $attributes = [])
 * @method static ArtistRepository|RepositoryProxy repository()
 * @method static Artist[]|Proxy[] all()
 * @method static Artist[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Artist[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Artist[]|Proxy[] findBy(array $attributes)
 * @method static Artist[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Artist[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Artist> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Artist> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Artist> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Artist> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Artist> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Artist> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Artist> random(array $attributes = [])
 * @phpstan-method static Proxy<Artist> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ArtistRepository> repository()
 * @phpstan-method static list<Proxy<Artist>> all()
 * @phpstan-method static list<Proxy<Artist>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Artist>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Artist>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Artist>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Artist>> randomSet(int $number, array $attributes = [])
 */
final class ArtistFactory extends ModelFactory
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

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        return [
            'biography' => self::faker()->text(),
            'countryCode' => self::faker()->text(3),
            'creationDatetime' => self::faker()->dateTime(),
            'labelName' => self::faker()->text(255),
            'members' => self::faker()->text(),
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Artist $artist): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Artist::class;
    }
}

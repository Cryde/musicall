<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Gallery>
 *
 * @method        Gallery|Proxy create(array|callable $attributes = [])
 * @method static Gallery|Proxy createOne(array $attributes = [])
 * @method static Gallery|Proxy find(object|array|mixed $criteria)
 * @method static Gallery|Proxy findOrCreate(array $attributes)
 * @method static Gallery|Proxy first(string $sortedField = 'id')
 * @method static Gallery|Proxy last(string $sortedField = 'id')
 * @method static Gallery|Proxy random(array $attributes = [])
 * @method static Gallery|Proxy randomOrCreate(array $attributes = [])
 * @method static GalleryRepository|RepositoryProxy repository()
 * @method static Gallery[]|Proxy[] all()
 * @method static Gallery[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Gallery[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Gallery[]|Proxy[] findBy(array $attributes)
 * @method static Gallery[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Gallery[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Gallery> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Gallery> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Gallery> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Gallery> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Gallery> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Gallery> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Gallery> random(array $attributes = [])
 * @phpstan-method static Proxy<Gallery> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<GalleryRepository> repository()
 * @phpstan-method static list<Proxy<Gallery>> all()
 * @phpstan-method static list<Proxy<Gallery>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Gallery>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Gallery>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Gallery>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Gallery>> randomSet(int $number, array $attributes = [])
 */
final class GalleryFactory extends ModelFactory
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
            'author' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
            'description' => self::faker()->text(),
            'publicationDatetime' => self::faker()->dateTime(),
            'slug' => self::faker()->text(255),
            'status' => self::faker()->numberBetween(1, 32767),
            'title' => self::faker()->text(255),
            'updateDatetime' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Gallery $gallery): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Gallery::class;
    }
}

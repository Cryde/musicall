<?php

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\ViewCache;
use App\Repository\Metric\ViewCacheRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ViewCache>
 *
 * @method        ViewCache|Proxy create(array|callable $attributes = [])
 * @method static ViewCache|Proxy createOne(array $attributes = [])
 * @method static ViewCache|Proxy find(object|array|mixed $criteria)
 * @method static ViewCache|Proxy findOrCreate(array $attributes)
 * @method static ViewCache|Proxy first(string $sortedField = 'id')
 * @method static ViewCache|Proxy last(string $sortedField = 'id')
 * @method static ViewCache|Proxy random(array $attributes = [])
 * @method static ViewCache|Proxy randomOrCreate(array $attributes = [])
 * @method static ViewCacheRepository|RepositoryProxy repository()
 * @method static ViewCache[]|Proxy[] all()
 * @method static ViewCache[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ViewCache[]|Proxy[] createSequence(array|callable $sequence)
 * @method static ViewCache[]|Proxy[] findBy(array $attributes)
 * @method static ViewCache[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ViewCache[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ViewCache> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ViewCache> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ViewCache> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ViewCache> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ViewCache> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ViewCache> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ViewCache> random(array $attributes = [])
 * @phpstan-method static Proxy<ViewCache> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ViewCacheRepository> repository()
 * @phpstan-method static list<Proxy<ViewCache>> all()
 * @phpstan-method static list<Proxy<ViewCache>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ViewCache>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<ViewCache>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ViewCache>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ViewCache>> randomSet(int $number, array $attributes = [])
 */
final class ViewCacheFactory extends ModelFactory
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
            'count' => self::faker()->randomNumber(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ViewCache $viewCache): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ViewCache::class;
    }
}

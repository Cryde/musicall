<?php

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\View;
use App\Repository\Metric\ViewRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<View>
 *
 * @method        View|Proxy create(array|callable $attributes = [])
 * @method static View|Proxy createOne(array $attributes = [])
 * @method static View|Proxy find(object|array|mixed $criteria)
 * @method static View|Proxy findOrCreate(array $attributes)
 * @method static View|Proxy first(string $sortedField = 'id')
 * @method static View|Proxy last(string $sortedField = 'id')
 * @method static View|Proxy random(array $attributes = [])
 * @method static View|Proxy randomOrCreate(array $attributes = [])
 * @method static ViewRepository|RepositoryProxy repository()
 * @method static View[]|Proxy[] all()
 * @method static View[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static View[]|Proxy[] createSequence(array|callable $sequence)
 * @method static View[]|Proxy[] findBy(array $attributes)
 * @method static View[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static View[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<View> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<View> createOne(array $attributes = [])
 * @phpstan-method static Proxy<View> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<View> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<View> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<View> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<View> random(array $attributes = [])
 * @phpstan-method static Proxy<View> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ViewRepository> repository()
 * @phpstan-method static list<Proxy<View>> all()
 * @phpstan-method static list<Proxy<View>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<View>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<View>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<View>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<View>> randomSet(int $number, array $attributes = [])
 */
final class ViewFactory extends ModelFactory
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
            'creationDatetime' => self::faker()->dateTime(),
            'identifier' => self::faker()->text(255),
            'viewCache' => ViewCacheFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(View $view): void {})
        ;
    }

    protected static function getClass(): string
    {
        return View::class;
    }
}

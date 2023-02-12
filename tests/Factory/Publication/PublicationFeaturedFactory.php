<?php

namespace App\Tests\Factory\Publication;

use App\Entity\PublicationFeatured;
use App\Repository\PublicationFeaturedRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<PublicationFeatured>
 *
 * @method        PublicationFeatured|Proxy create(array|callable $attributes = [])
 * @method static PublicationFeatured|Proxy createOne(array $attributes = [])
 * @method static PublicationFeatured|Proxy find(object|array|mixed $criteria)
 * @method static PublicationFeatured|Proxy findOrCreate(array $attributes)
 * @method static PublicationFeatured|Proxy first(string $sortedField = 'id')
 * @method static PublicationFeatured|Proxy last(string $sortedField = 'id')
 * @method static PublicationFeatured|Proxy random(array $attributes = [])
 * @method static PublicationFeatured|Proxy randomOrCreate(array $attributes = [])
 * @method static PublicationFeaturedRepository|RepositoryProxy repository()
 * @method static PublicationFeatured[]|Proxy[] all()
 * @method static PublicationFeatured[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PublicationFeatured[]|Proxy[] createSequence(array|callable $sequence)
 * @method static PublicationFeatured[]|Proxy[] findBy(array $attributes)
 * @method static PublicationFeatured[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PublicationFeatured[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PublicationFeatured> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PublicationFeatured> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PublicationFeatured> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PublicationFeatured> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PublicationFeatured> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationFeatured> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationFeatured> random(array $attributes = [])
 * @phpstan-method static Proxy<PublicationFeatured> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<PublicationFeaturedRepository> repository()
 * @phpstan-method static list<Proxy<PublicationFeatured>> all()
 * @phpstan-method static list<Proxy<PublicationFeatured>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<PublicationFeatured>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<PublicationFeatured>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<PublicationFeatured>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationFeatured>> randomSet(int $number, array $attributes = [])
 */
final class PublicationFeaturedFactory extends ModelFactory
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
            'description' => self::faker()->text(),
            'level' => self::faker()->numberBetween(1, 32767),
            'options' => [],
            'publication' => PublicationFactory::new(),
            'publicationDatetime' => self::faker()->dateTime(),
            'status' => self::faker()->numberBetween(1, 32767),
            'title' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(PublicationFeatured $publicationFeatured): void {})
        ;
    }

    protected static function getClass(): string
    {
        return PublicationFeatured::class;
    }
}

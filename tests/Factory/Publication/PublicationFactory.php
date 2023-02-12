<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Publication>
 *
 * @method        Publication|Proxy create(array|callable $attributes = [])
 * @method static Publication|Proxy createOne(array $attributes = [])
 * @method static Publication|Proxy find(object|array|mixed $criteria)
 * @method static Publication|Proxy findOrCreate(array $attributes)
 * @method static Publication|Proxy first(string $sortedField = 'id')
 * @method static Publication|Proxy last(string $sortedField = 'id')
 * @method static Publication|Proxy random(array $attributes = [])
 * @method static Publication|Proxy randomOrCreate(array $attributes = [])
 * @method static PublicationRepository|RepositoryProxy repository()
 * @method static Publication[]|Proxy[] all()
 * @method static Publication[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Publication[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Publication[]|Proxy[] findBy(array $attributes)
 * @method static Publication[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Publication[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Publication> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Publication> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Publication> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Publication> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Publication> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Publication> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Publication> random(array $attributes = [])
 * @phpstan-method static Proxy<Publication> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<PublicationRepository> repository()
 * @phpstan-method static list<Proxy<Publication>> all()
 * @phpstan-method static list<Proxy<Publication>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Publication>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Publication>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Publication>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Publication>> randomSet(int $number, array $attributes = [])
 */
final class PublicationFactory extends ModelFactory
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
            'content' => self::faker()->text(),
            'creationDatetime' => self::faker()->dateTime(),
            'editionDatetime' => self::faker()->dateTime(),
            'publicationDatetime' => self::faker()->dateTime(),
            'shortDescription' => self::faker()->text(),
            'slug' => self::faker()->text(255),
            'status' => self::faker()->numberBetween(1, 32767),
            'subCategory' => PublicationSubCategoryFactory::new(),
            'title' => self::faker()->text(255),
            'type' => self::faker()->numberBetween(1, 32767),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Publication $publication): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Publication::class;
    }
}

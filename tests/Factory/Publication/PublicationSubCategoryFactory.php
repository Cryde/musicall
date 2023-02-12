<?php

namespace App\Tests\Factory\Publication;

use App\Entity\PublicationSubCategory;
use App\Repository\PublicationSubCategoryRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<PublicationSubCategory>
 *
 * @method        PublicationSubCategory|Proxy create(array|callable $attributes = [])
 * @method static PublicationSubCategory|Proxy createOne(array $attributes = [])
 * @method static PublicationSubCategory|Proxy find(object|array|mixed $criteria)
 * @method static PublicationSubCategory|Proxy findOrCreate(array $attributes)
 * @method static PublicationSubCategory|Proxy first(string $sortedField = 'id')
 * @method static PublicationSubCategory|Proxy last(string $sortedField = 'id')
 * @method static PublicationSubCategory|Proxy random(array $attributes = [])
 * @method static PublicationSubCategory|Proxy randomOrCreate(array $attributes = [])
 * @method static PublicationSubCategoryRepository|RepositoryProxy repository()
 * @method static PublicationSubCategory[]|Proxy[] all()
 * @method static PublicationSubCategory[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PublicationSubCategory[]|Proxy[] createSequence(array|callable $sequence)
 * @method static PublicationSubCategory[]|Proxy[] findBy(array $attributes)
 * @method static PublicationSubCategory[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PublicationSubCategory[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PublicationSubCategory> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PublicationSubCategory> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PublicationSubCategory> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PublicationSubCategory> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PublicationSubCategory> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationSubCategory> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationSubCategory> random(array $attributes = [])
 * @phpstan-method static Proxy<PublicationSubCategory> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<PublicationSubCategoryRepository> repository()
 * @phpstan-method static list<Proxy<PublicationSubCategory>> all()
 * @phpstan-method static list<Proxy<PublicationSubCategory>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<PublicationSubCategory>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<PublicationSubCategory>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<PublicationSubCategory>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationSubCategory>> randomSet(int $number, array $attributes = [])
 */
final class PublicationSubCategoryFactory extends ModelFactory
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
            'position' => self::faker()->randomNumber(),
            'slug' => self::faker()->text(255),
            'title' => self::faker()->text(255),
            'type' => PublicationSubCategory::TYPE_PUBLICATION,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(PublicationSubCategory $publicationSubCategory): void {})
        ;
    }

    public function asNews()
    {
        return $this->addState(['title' => 'News', 'slug' => 'news', 'position' => 1]);
    }

    public function asChronique()
    {
        return $this->addState(['title' => 'Chroniques', 'slug' => 'chroniques', 'position' => 2]);
    }

    public function asInterview()
    {
        return $this->addState(['title' => 'Interviews', 'slug' => 'interviews', 'position' => 3]);
    }

    public function asLiveReports()
    {
        return $this->addState(['title' => 'Live-reports', 'slug' => 'live-reports', 'position' => 4]);
    }

    public function asArticle()
    {
        return $this->addState(['title' => 'Articles', 'slug' => 'articles', 'position' => 5]);
    }

    public function asDecouvertes()
    {
        return $this->addState(['title' => 'Découvertes', 'slug' => 'decouvertes', 'position' => 6]);
    }


    protected static function getClass(): string
    {
        return PublicationSubCategory::class;
    }
}

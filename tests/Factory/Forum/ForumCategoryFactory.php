<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumCategory;
use App\Repository\Forum\ForumCategoryRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ForumCategory>
 *
 * @method        ForumCategory|Proxy create(array|callable $attributes = [])
 * @method static ForumCategory|Proxy createOne(array $attributes = [])
 * @method static ForumCategory|Proxy find(object|array|mixed $criteria)
 * @method static ForumCategory|Proxy findOrCreate(array $attributes)
 * @method static ForumCategory|Proxy first(string $sortedField = 'id')
 * @method static ForumCategory|Proxy last(string $sortedField = 'id')
 * @method static ForumCategory|Proxy random(array $attributes = [])
 * @method static ForumCategory|Proxy randomOrCreate(array $attributes = [])
 * @method static ForumCategoryRepository|RepositoryProxy repository()
 * @method static ForumCategory[]|Proxy[] all()
 * @method static ForumCategory[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ForumCategory[]|Proxy[] createSequence(array|callable $sequence)
 * @method static ForumCategory[]|Proxy[] findBy(array $attributes)
 * @method static ForumCategory[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ForumCategory[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ForumCategory> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ForumCategory> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ForumCategory> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ForumCategory> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ForumCategory> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumCategory> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumCategory> random(array $attributes = [])
 * @phpstan-method static Proxy<ForumCategory> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ForumCategoryRepository> repository()
 * @phpstan-method static list<Proxy<ForumCategory>> all()
 * @phpstan-method static list<Proxy<ForumCategory>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ForumCategory>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<ForumCategory>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ForumCategory>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ForumCategory>> randomSet(int $number, array $attributes = [])
 */
final class ForumCategoryFactory extends ModelFactory
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
            'forumSource' => ForumSourceFactory::new(),
            'position' => self::faker()->randomNumber(),
            'title' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ForumCategory $forumCategory): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ForumCategory::class;
    }
}

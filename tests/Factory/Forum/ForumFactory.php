<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\Forum;
use App\Repository\Forum\ForumRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Forum>
 *
 * @method        Forum|Proxy create(array|callable $attributes = [])
 * @method static Forum|Proxy createOne(array $attributes = [])
 * @method static Forum|Proxy find(object|array|mixed $criteria)
 * @method static Forum|Proxy findOrCreate(array $attributes)
 * @method static Forum|Proxy first(string $sortedField = 'id')
 * @method static Forum|Proxy last(string $sortedField = 'id')
 * @method static Forum|Proxy random(array $attributes = [])
 * @method static Forum|Proxy randomOrCreate(array $attributes = [])
 * @method static ForumRepository|RepositoryProxy repository()
 * @method static Forum[]|Proxy[] all()
 * @method static Forum[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Forum[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Forum[]|Proxy[] findBy(array $attributes)
 * @method static Forum[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Forum[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Forum> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Forum> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Forum> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Forum> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Forum> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Forum> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Forum> random(array $attributes = [])
 * @phpstan-method static Proxy<Forum> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ForumRepository> repository()
 * @phpstan-method static list<Proxy<Forum>> all()
 * @phpstan-method static list<Proxy<Forum>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Forum>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Forum>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Forum>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Forum>> randomSet(int $number, array $attributes = [])
 */
final class ForumFactory extends ModelFactory
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
            'forumCategory' => ForumCategoryFactory::new(),
            'position' => self::faker()->randomNumber(),
            'postNumber' => self::faker()->randomNumber(),
            'slug' => self::faker()->text(255),
            'title' => self::faker()->text(255),
            'topicNumber' => self::faker()->randomNumber(),
            'updateDatetime' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Forum $forum): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Forum::class;
    }
}

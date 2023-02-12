<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumSource;
use App\Repository\Forum\ForumSourceRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ForumSource>
 *
 * @method        ForumSource|Proxy create(array|callable $attributes = [])
 * @method static ForumSource|Proxy createOne(array $attributes = [])
 * @method static ForumSource|Proxy find(object|array|mixed $criteria)
 * @method static ForumSource|Proxy findOrCreate(array $attributes)
 * @method static ForumSource|Proxy first(string $sortedField = 'id')
 * @method static ForumSource|Proxy last(string $sortedField = 'id')
 * @method static ForumSource|Proxy random(array $attributes = [])
 * @method static ForumSource|Proxy randomOrCreate(array $attributes = [])
 * @method static ForumSourceRepository|RepositoryProxy repository()
 * @method static ForumSource[]|Proxy[] all()
 * @method static ForumSource[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ForumSource[]|Proxy[] createSequence(array|callable $sequence)
 * @method static ForumSource[]|Proxy[] findBy(array $attributes)
 * @method static ForumSource[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ForumSource[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ForumSource> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ForumSource> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ForumSource> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ForumSource> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ForumSource> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumSource> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumSource> random(array $attributes = [])
 * @phpstan-method static Proxy<ForumSource> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ForumSourceRepository> repository()
 * @phpstan-method static list<Proxy<ForumSource>> all()
 * @phpstan-method static list<Proxy<ForumSource>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ForumSource>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<ForumSource>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ForumSource>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ForumSource>> randomSet(int $number, array $attributes = [])
 */
final class ForumSourceFactory extends ModelFactory
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
            'description' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ForumSource $forumSource): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ForumSource::class;
    }
}

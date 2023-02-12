<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumPost;
use App\Repository\Forum\ForumPostRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ForumPost>
 *
 * @method        ForumPost|Proxy create(array|callable $attributes = [])
 * @method static ForumPost|Proxy createOne(array $attributes = [])
 * @method static ForumPost|Proxy find(object|array|mixed $criteria)
 * @method static ForumPost|Proxy findOrCreate(array $attributes)
 * @method static ForumPost|Proxy first(string $sortedField = 'id')
 * @method static ForumPost|Proxy last(string $sortedField = 'id')
 * @method static ForumPost|Proxy random(array $attributes = [])
 * @method static ForumPost|Proxy randomOrCreate(array $attributes = [])
 * @method static ForumPostRepository|RepositoryProxy repository()
 * @method static ForumPost[]|Proxy[] all()
 * @method static ForumPost[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ForumPost[]|Proxy[] createSequence(array|callable $sequence)
 * @method static ForumPost[]|Proxy[] findBy(array $attributes)
 * @method static ForumPost[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ForumPost[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ForumPost> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ForumPost> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ForumPost> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ForumPost> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ForumPost> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumPost> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumPost> random(array $attributes = [])
 * @phpstan-method static Proxy<ForumPost> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ForumPostRepository> repository()
 * @phpstan-method static list<Proxy<ForumPost>> all()
 * @phpstan-method static list<Proxy<ForumPost>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ForumPost>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<ForumPost>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ForumPost>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ForumPost>> randomSet(int $number, array $attributes = [])
 */
final class ForumPostFactory extends ModelFactory
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
            'content' => self::faker()->text(),
            'creationDatetime' => self::faker()->dateTime(),
            'creator' => UserFactory::new(),
            'topic' => ForumTopicFactory::new(),
            'updateDatetime' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ForumPost $forumPost): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ForumPost::class;
    }
}

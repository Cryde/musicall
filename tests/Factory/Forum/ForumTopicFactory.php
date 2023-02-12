<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumTopic;
use App\Repository\Forum\ForumTopicRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ForumTopic>
 *
 * @method        ForumTopic|Proxy create(array|callable $attributes = [])
 * @method static ForumTopic|Proxy createOne(array $attributes = [])
 * @method static ForumTopic|Proxy find(object|array|mixed $criteria)
 * @method static ForumTopic|Proxy findOrCreate(array $attributes)
 * @method static ForumTopic|Proxy first(string $sortedField = 'id')
 * @method static ForumTopic|Proxy last(string $sortedField = 'id')
 * @method static ForumTopic|Proxy random(array $attributes = [])
 * @method static ForumTopic|Proxy randomOrCreate(array $attributes = [])
 * @method static ForumTopicRepository|RepositoryProxy repository()
 * @method static ForumTopic[]|Proxy[] all()
 * @method static ForumTopic[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ForumTopic[]|Proxy[] createSequence(array|callable $sequence)
 * @method static ForumTopic[]|Proxy[] findBy(array $attributes)
 * @method static ForumTopic[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ForumTopic[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ForumTopic> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ForumTopic> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ForumTopic> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ForumTopic> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ForumTopic> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumTopic> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ForumTopic> random(array $attributes = [])
 * @phpstan-method static Proxy<ForumTopic> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ForumTopicRepository> repository()
 * @phpstan-method static list<Proxy<ForumTopic>> all()
 * @phpstan-method static list<Proxy<ForumTopic>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ForumTopic>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<ForumTopic>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ForumTopic>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ForumTopic>> randomSet(int $number, array $attributes = [])
 */
final class ForumTopicFactory extends ModelFactory
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
            'forum' => ForumFactory::new(),
            'isLocked' => self::faker()->boolean(),
            'postNumber' => self::faker()->randomNumber(),
            'slug' => self::faker()->text(255),
            'title' => self::faker()->text(255),
            'type' => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ForumTopic $forumTopic): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ForumTopic::class;
    }
}

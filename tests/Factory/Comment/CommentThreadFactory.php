<?php

namespace App\Tests\Factory\Comment;

use App\Entity\Comment\CommentThread;
use App\Repository\Comment\CommentThreadRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CommentThread>
 *
 * @method        CommentThread|Proxy create(array|callable $attributes = [])
 * @method static CommentThread|Proxy createOne(array $attributes = [])
 * @method static CommentThread|Proxy find(object|array|mixed $criteria)
 * @method static CommentThread|Proxy findOrCreate(array $attributes)
 * @method static CommentThread|Proxy first(string $sortedField = 'id')
 * @method static CommentThread|Proxy last(string $sortedField = 'id')
 * @method static CommentThread|Proxy random(array $attributes = [])
 * @method static CommentThread|Proxy randomOrCreate(array $attributes = [])
 * @method static CommentThreadRepository|RepositoryProxy repository()
 * @method static CommentThread[]|Proxy[] all()
 * @method static CommentThread[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CommentThread[]|Proxy[] createSequence(array|callable $sequence)
 * @method static CommentThread[]|Proxy[] findBy(array $attributes)
 * @method static CommentThread[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CommentThread[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CommentThread> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CommentThread> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CommentThread> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CommentThread> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CommentThread> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CommentThread> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CommentThread> random(array $attributes = [])
 * @phpstan-method static Proxy<CommentThread> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CommentThreadRepository> repository()
 * @phpstan-method static list<Proxy<CommentThread>> all()
 * @phpstan-method static list<Proxy<CommentThread>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<CommentThread>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<CommentThread>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<CommentThread>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<CommentThread>> randomSet(int $number, array $attributes = [])
 */
final class CommentThreadFactory extends ModelFactory
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
            'commentNumber' => self::faker()->randomNumber(),
            'creationDatetime' => self::faker()->dateTime(),
            'isActive' => self::faker()->boolean(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(CommentThread $commentThread): void {})
        ;
    }

    protected static function getClass(): string
    {
        return CommentThread::class;
    }
}

<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageThread;
use App\Repository\Message\MessageThreadRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<MessageThread>
 *
 * @method        MessageThread|Proxy create(array|callable $attributes = [])
 * @method static MessageThread|Proxy createOne(array $attributes = [])
 * @method static MessageThread|Proxy find(object|array|mixed $criteria)
 * @method static MessageThread|Proxy findOrCreate(array $attributes)
 * @method static MessageThread|Proxy first(string $sortedField = 'id')
 * @method static MessageThread|Proxy last(string $sortedField = 'id')
 * @method static MessageThread|Proxy random(array $attributes = [])
 * @method static MessageThread|Proxy randomOrCreate(array $attributes = [])
 * @method static MessageThreadRepository|RepositoryProxy repository()
 * @method static MessageThread[]|Proxy[] all()
 * @method static MessageThread[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MessageThread[]|Proxy[] createSequence(array|callable $sequence)
 * @method static MessageThread[]|Proxy[] findBy(array $attributes)
 * @method static MessageThread[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MessageThread[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<MessageThread> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<MessageThread> createOne(array $attributes = [])
 * @phpstan-method static Proxy<MessageThread> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<MessageThread> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<MessageThread> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<MessageThread> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<MessageThread> random(array $attributes = [])
 * @phpstan-method static Proxy<MessageThread> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<MessageThreadRepository> repository()
 * @phpstan-method static list<Proxy<MessageThread>> all()
 * @phpstan-method static list<Proxy<MessageThread>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<MessageThread>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<MessageThread>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<MessageThread>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<MessageThread>> randomSet(int $number, array $attributes = [])
 */
final class MessageThreadFactory extends ModelFactory
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
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(MessageThread $messageThread): void {})
        ;
    }

    protected static function getClass(): string
    {
        return MessageThread::class;
    }
}

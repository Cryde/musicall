<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\Message;
use App\Repository\Message\MessageRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Message>
 *
 * @method        Message|Proxy create(array|callable $attributes = [])
 * @method static Message|Proxy createOne(array $attributes = [])
 * @method static Message|Proxy find(object|array|mixed $criteria)
 * @method static Message|Proxy findOrCreate(array $attributes)
 * @method static Message|Proxy first(string $sortedField = 'id')
 * @method static Message|Proxy last(string $sortedField = 'id')
 * @method static Message|Proxy random(array $attributes = [])
 * @method static Message|Proxy randomOrCreate(array $attributes = [])
 * @method static MessageRepository|RepositoryProxy repository()
 * @method static Message[]|Proxy[] all()
 * @method static Message[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Message[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Message[]|Proxy[] findBy(array $attributes)
 * @method static Message[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Message[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Message> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Message> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Message> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Message> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Message> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Message> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Message> random(array $attributes = [])
 * @phpstan-method static Proxy<Message> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<MessageRepository> repository()
 * @phpstan-method static list<Proxy<Message>> all()
 * @phpstan-method static list<Proxy<Message>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Message>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Message>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Message>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Message>> randomSet(int $number, array $attributes = [])
 */
final class MessageFactory extends ModelFactory
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
            'thread' => MessageThreadFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Message $message): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Message::class;
    }
}

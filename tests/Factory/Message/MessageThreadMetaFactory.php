<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageThreadMeta;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<MessageThreadMeta>
 *
 * @method        MessageThreadMeta|Proxy create(array|callable $attributes = [])
 * @method static MessageThreadMeta|Proxy createOne(array $attributes = [])
 * @method static MessageThreadMeta|Proxy find(object|array|mixed $criteria)
 * @method static MessageThreadMeta|Proxy findOrCreate(array $attributes)
 * @method static MessageThreadMeta|Proxy first(string $sortedField = 'id')
 * @method static MessageThreadMeta|Proxy last(string $sortedField = 'id')
 * @method static MessageThreadMeta|Proxy random(array $attributes = [])
 * @method static MessageThreadMeta|Proxy randomOrCreate(array $attributes = [])
 * @method static MessageThreadMetaRepository|RepositoryProxy repository()
 * @method static MessageThreadMeta[]|Proxy[] all()
 * @method static MessageThreadMeta[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MessageThreadMeta[]|Proxy[] createSequence(array|callable $sequence)
 * @method static MessageThreadMeta[]|Proxy[] findBy(array $attributes)
 * @method static MessageThreadMeta[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MessageThreadMeta[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<MessageThreadMeta> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<MessageThreadMeta> createOne(array $attributes = [])
 * @phpstan-method static Proxy<MessageThreadMeta> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<MessageThreadMeta> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<MessageThreadMeta> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<MessageThreadMeta> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<MessageThreadMeta> random(array $attributes = [])
 * @phpstan-method static Proxy<MessageThreadMeta> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<MessageThreadMetaRepository> repository()
 * @phpstan-method static list<Proxy<MessageThreadMeta>> all()
 * @phpstan-method static list<Proxy<MessageThreadMeta>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<MessageThreadMeta>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<MessageThreadMeta>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<MessageThreadMeta>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<MessageThreadMeta>> randomSet(int $number, array $attributes = [])
 */
final class MessageThreadMetaFactory extends ModelFactory
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
            'creationDatetime' => new \DateTime(),
            'isDeleted' => false,
            'isRead' => false,
            'thread' => MessageThreadFactory::new(),
            'user' => UserFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(MessageThreadMeta $messageThreadMeta): void {})
        ;
    }

    protected static function getClass(): string
    {
        return MessageThreadMeta::class;
    }
}

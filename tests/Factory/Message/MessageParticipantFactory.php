<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageParticipant;
use App\Repository\Message\MessageParticipantRepository;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<MessageParticipant>
 *
 * @method        MessageParticipant|Proxy create(array|callable $attributes = [])
 * @method static MessageParticipant|Proxy createOne(array $attributes = [])
 * @method static MessageParticipant|Proxy find(object|array|mixed $criteria)
 * @method static MessageParticipant|Proxy findOrCreate(array $attributes)
 * @method static MessageParticipant|Proxy first(string $sortedField = 'id')
 * @method static MessageParticipant|Proxy last(string $sortedField = 'id')
 * @method static MessageParticipant|Proxy random(array $attributes = [])
 * @method static MessageParticipant|Proxy randomOrCreate(array $attributes = [])
 * @method static MessageParticipantRepository|RepositoryProxy repository()
 * @method static MessageParticipant[]|Proxy[] all()
 * @method static MessageParticipant[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MessageParticipant[]|Proxy[] createSequence(array|callable $sequence)
 * @method static MessageParticipant[]|Proxy[] findBy(array $attributes)
 * @method static MessageParticipant[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MessageParticipant[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<MessageParticipant> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<MessageParticipant> createOne(array $attributes = [])
 * @phpstan-method static Proxy<MessageParticipant> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<MessageParticipant> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<MessageParticipant> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<MessageParticipant> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<MessageParticipant> random(array $attributes = [])
 * @phpstan-method static Proxy<MessageParticipant> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<MessageParticipantRepository> repository()
 * @phpstan-method static list<Proxy<MessageParticipant>> all()
 * @phpstan-method static list<Proxy<MessageParticipant>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<MessageParticipant>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<MessageParticipant>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<MessageParticipant>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<MessageParticipant>> randomSet(int $number, array $attributes = [])
 */
final class MessageParticipantFactory extends ModelFactory
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
            'participant' => UserFactory::new(),
            'thread' => MessageThreadFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(MessageParticipant $messageParticipant): void {})
        ;
    }

    protected static function getClass(): string
    {
        return MessageParticipant::class;
    }
}

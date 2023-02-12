<?php

namespace App\Tests\Factory\Attribute;

use App\Entity\Attribute\Instrument;
use App\Repository\Attribute\InstrumentRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Instrument>
 *
 * @method        Instrument|Proxy create(array|callable $attributes = [])
 * @method static Instrument|Proxy createOne(array $attributes = [])
 * @method static Instrument|Proxy find(object|array|mixed $criteria)
 * @method static Instrument|Proxy findOrCreate(array $attributes)
 * @method static Instrument|Proxy first(string $sortedField = 'id')
 * @method static Instrument|Proxy last(string $sortedField = 'id')
 * @method static Instrument|Proxy random(array $attributes = [])
 * @method static Instrument|Proxy randomOrCreate(array $attributes = [])
 * @method static InstrumentRepository|RepositoryProxy repository()
 * @method static Instrument[]|Proxy[] all()
 * @method static Instrument[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Instrument[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Instrument[]|Proxy[] findBy(array $attributes)
 * @method static Instrument[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Instrument[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Instrument> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Instrument> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Instrument> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Instrument> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Instrument> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Instrument> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Instrument> random(array $attributes = [])
 * @phpstan-method static Proxy<Instrument> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<InstrumentRepository> repository()
 * @phpstan-method static list<Proxy<Instrument>> all()
 * @phpstan-method static list<Proxy<Instrument>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Instrument>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Instrument>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Instrument>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Instrument>> randomSet(int $number, array $attributes = [])
 */
final class InstrumentFactory extends ModelFactory
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
            'musicianName' => self::faker()->text(255),
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Instrument $instrument): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Instrument::class;
    }
}

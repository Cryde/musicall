<?php

namespace App\Tests\Factory\Attribute;

use App\Entity\Attribute\Style;
use App\Repository\Attribute\StyleRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Style>
 *
 * @method        Style|Proxy create(array|callable $attributes = [])
 * @method static Style|Proxy createOne(array $attributes = [])
 * @method static Style|Proxy find(object|array|mixed $criteria)
 * @method static Style|Proxy findOrCreate(array $attributes)
 * @method static Style|Proxy first(string $sortedField = 'id')
 * @method static Style|Proxy last(string $sortedField = 'id')
 * @method static Style|Proxy random(array $attributes = [])
 * @method static Style|Proxy randomOrCreate(array $attributes = [])
 * @method static StyleRepository|RepositoryProxy repository()
 * @method static Style[]|Proxy[] all()
 * @method static Style[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Style[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Style[]|Proxy[] findBy(array $attributes)
 * @method static Style[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Style[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Style> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Style> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Style> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Style> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Style> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Style> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Style> random(array $attributes = [])
 * @phpstan-method static Proxy<Style> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<StyleRepository> repository()
 * @phpstan-method static list<Proxy<Style>> all()
 * @phpstan-method static list<Proxy<Style>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<Style>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<Style>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<Style>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<Style>> randomSet(int $number, array $attributes = [])
 */
final class StyleFactory extends ModelFactory
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
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public function asRock()
    {
        return $this->addState([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'name' => 'Rock',
            'slug' => 'rock',
        ]);
    }

    public function asPop()
    {
        return $this->addState([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'name' => 'Pop',
            'slug' => 'pop',
        ]);
    }

    public function asMetal()
    {
        return $this->addState([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'name' => 'Metal',
            'slug' => 'metal',
        ]);
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Style $style): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Style::class;
    }
}

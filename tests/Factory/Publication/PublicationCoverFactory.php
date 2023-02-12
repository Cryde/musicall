<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationCover;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<PublicationCover>
 *
 * @method        PublicationCover|Proxy create(array|callable $attributes = [])
 * @method static PublicationCover|Proxy createOne(array $attributes = [])
 * @method static PublicationCover|Proxy find(object|array|mixed $criteria)
 * @method static PublicationCover|Proxy findOrCreate(array $attributes)
 * @method static PublicationCover|Proxy first(string $sortedField = 'id')
 * @method static PublicationCover|Proxy last(string $sortedField = 'id')
 * @method static PublicationCover|Proxy random(array $attributes = [])
 * @method static PublicationCover|Proxy randomOrCreate(array $attributes = [])
 * @method static PublicationCover[]|Proxy[] all()
 * @method static PublicationCover[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PublicationCover[]|Proxy[] createSequence(array|callable $sequence)
 * @method static PublicationCover[]|Proxy[] findBy(array $attributes)
 * @method static PublicationCover[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PublicationCover[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PublicationCover> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PublicationCover> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PublicationCover> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PublicationCover> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PublicationCover> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationCover> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationCover> random(array $attributes = [])
 * @phpstan-method static Proxy<PublicationCover> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationCover>> all()
 * @phpstan-method static list<Proxy<PublicationCover>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<PublicationCover>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<PublicationCover>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<PublicationCover>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationCover>> randomSet(int $number, array $attributes = [])
 */
final class PublicationCoverFactory extends ModelFactory
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
            'imageName' => self::faker()->text(255),
            'imageSize' => self::faker()->randomNumber(),
            'updatedAt' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(PublicationCover $publicationCover): void {})
        ;
    }

    protected static function getClass(): string
    {
        return PublicationCover::class;
    }
}

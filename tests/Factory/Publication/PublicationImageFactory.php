<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationImage;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<PublicationImage>
 *
 * @method        PublicationImage|Proxy create(array|callable $attributes = [])
 * @method static PublicationImage|Proxy createOne(array $attributes = [])
 * @method static PublicationImage|Proxy find(object|array|mixed $criteria)
 * @method static PublicationImage|Proxy findOrCreate(array $attributes)
 * @method static PublicationImage|Proxy first(string $sortedField = 'id')
 * @method static PublicationImage|Proxy last(string $sortedField = 'id')
 * @method static PublicationImage|Proxy random(array $attributes = [])
 * @method static PublicationImage|Proxy randomOrCreate(array $attributes = [])
 * @method static PublicationImage[]|Proxy[] all()
 * @method static PublicationImage[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PublicationImage[]|Proxy[] createSequence(array|callable $sequence)
 * @method static PublicationImage[]|Proxy[] findBy(array $attributes)
 * @method static PublicationImage[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PublicationImage[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PublicationImage> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PublicationImage> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PublicationImage> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PublicationImage> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PublicationImage> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationImage> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationImage> random(array $attributes = [])
 * @phpstan-method static Proxy<PublicationImage> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationImage>> all()
 * @phpstan-method static list<Proxy<PublicationImage>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<PublicationImage>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<PublicationImage>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<PublicationImage>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationImage>> randomSet(int $number, array $attributes = [])
 */
final class PublicationImageFactory extends ModelFactory
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
            // ->afterInstantiate(function(PublicationImage $publicationImage): void {})
        ;
    }

    protected static function getClass(): string
    {
        return PublicationImage::class;
    }
}

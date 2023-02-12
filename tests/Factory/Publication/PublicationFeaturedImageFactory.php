<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationFeaturedImage;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<PublicationFeaturedImage>
 *
 * @method        PublicationFeaturedImage|Proxy create(array|callable $attributes = [])
 * @method static PublicationFeaturedImage|Proxy createOne(array $attributes = [])
 * @method static PublicationFeaturedImage|Proxy find(object|array|mixed $criteria)
 * @method static PublicationFeaturedImage|Proxy findOrCreate(array $attributes)
 * @method static PublicationFeaturedImage|Proxy first(string $sortedField = 'id')
 * @method static PublicationFeaturedImage|Proxy last(string $sortedField = 'id')
 * @method static PublicationFeaturedImage|Proxy random(array $attributes = [])
 * @method static PublicationFeaturedImage|Proxy randomOrCreate(array $attributes = [])
 * @method static PublicationFeaturedImage[]|Proxy[] all()
 * @method static PublicationFeaturedImage[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PublicationFeaturedImage[]|Proxy[] createSequence(array|callable $sequence)
 * @method static PublicationFeaturedImage[]|Proxy[] findBy(array $attributes)
 * @method static PublicationFeaturedImage[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PublicationFeaturedImage[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PublicationFeaturedImage> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PublicationFeaturedImage> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PublicationFeaturedImage> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PublicationFeaturedImage> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PublicationFeaturedImage> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationFeaturedImage> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PublicationFeaturedImage> random(array $attributes = [])
 * @phpstan-method static Proxy<PublicationFeaturedImage> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationFeaturedImage>> all()
 * @phpstan-method static list<Proxy<PublicationFeaturedImage>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<PublicationFeaturedImage>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<PublicationFeaturedImage>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<PublicationFeaturedImage>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<PublicationFeaturedImage>> randomSet(int $number, array $attributes = [])
 */
final class PublicationFeaturedImageFactory extends ModelFactory
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
            // ->afterInstantiate(function(PublicationFeaturedImage $publicationFeaturedImage): void {})
        ;
    }

    protected static function getClass(): string
    {
        return PublicationFeaturedImage::class;
    }
}

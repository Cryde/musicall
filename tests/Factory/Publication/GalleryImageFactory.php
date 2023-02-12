<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\GalleryImage;
use App\Repository\GalleryImageRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<GalleryImage>
 *
 * @method        GalleryImage|Proxy create(array|callable $attributes = [])
 * @method static GalleryImage|Proxy createOne(array $attributes = [])
 * @method static GalleryImage|Proxy find(object|array|mixed $criteria)
 * @method static GalleryImage|Proxy findOrCreate(array $attributes)
 * @method static GalleryImage|Proxy first(string $sortedField = 'id')
 * @method static GalleryImage|Proxy last(string $sortedField = 'id')
 * @method static GalleryImage|Proxy random(array $attributes = [])
 * @method static GalleryImage|Proxy randomOrCreate(array $attributes = [])
 * @method static GalleryImageRepository|RepositoryProxy repository()
 * @method static GalleryImage[]|Proxy[] all()
 * @method static GalleryImage[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static GalleryImage[]|Proxy[] createSequence(array|callable $sequence)
 * @method static GalleryImage[]|Proxy[] findBy(array $attributes)
 * @method static GalleryImage[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static GalleryImage[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<GalleryImage> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<GalleryImage> createOne(array $attributes = [])
 * @phpstan-method static Proxy<GalleryImage> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<GalleryImage> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<GalleryImage> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<GalleryImage> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<GalleryImage> random(array $attributes = [])
 * @phpstan-method static Proxy<GalleryImage> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<GalleryImageRepository> repository()
 * @phpstan-method static list<Proxy<GalleryImage>> all()
 * @phpstan-method static list<Proxy<GalleryImage>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<GalleryImage>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<GalleryImage>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<GalleryImage>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<GalleryImage>> randomSet(int $number, array $attributes = [])
 */
final class GalleryImageFactory extends ModelFactory
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
            'gallery' => GalleryFactory::new(),
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
            // ->afterInstantiate(function(GalleryImage $galleryImage): void {})
        ;
    }

    protected static function getClass(): string
    {
        return GalleryImage::class;
    }
}

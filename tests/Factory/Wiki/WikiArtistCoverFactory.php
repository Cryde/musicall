<?php

namespace App\Tests\Factory\Wiki;

use App\Entity\Image\WikiArtistCover;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<WikiArtistCover>
 *
 * @method        WikiArtistCover|Proxy create(array|callable $attributes = [])
 * @method static WikiArtistCover|Proxy createOne(array $attributes = [])
 * @method static WikiArtistCover|Proxy find(object|array|mixed $criteria)
 * @method static WikiArtistCover|Proxy findOrCreate(array $attributes)
 * @method static WikiArtistCover|Proxy first(string $sortedField = 'id')
 * @method static WikiArtistCover|Proxy last(string $sortedField = 'id')
 * @method static WikiArtistCover|Proxy random(array $attributes = [])
 * @method static WikiArtistCover|Proxy randomOrCreate(array $attributes = [])
 * @method static WikiArtistCover[]|Proxy[] all()
 * @method static WikiArtistCover[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static WikiArtistCover[]|Proxy[] createSequence(array|callable $sequence)
 * @method static WikiArtistCover[]|Proxy[] findBy(array $attributes)
 * @method static WikiArtistCover[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static WikiArtistCover[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<WikiArtistCover> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<WikiArtistCover> createOne(array $attributes = [])
 * @phpstan-method static Proxy<WikiArtistCover> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<WikiArtistCover> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<WikiArtistCover> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<WikiArtistCover> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<WikiArtistCover> random(array $attributes = [])
 * @phpstan-method static Proxy<WikiArtistCover> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<Proxy<WikiArtistCover>> all()
 * @phpstan-method static list<Proxy<WikiArtistCover>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<WikiArtistCover>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<WikiArtistCover>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<WikiArtistCover>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<WikiArtistCover>> randomSet(int $number, array $attributes = [])
 */
final class WikiArtistCoverFactory extends ModelFactory
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
            // ->afterInstantiate(function(WikiArtistCover $wikiArtistCover): void {})
        ;
    }

    protected static function getClass(): string
    {
        return WikiArtistCover::class;
    }
}

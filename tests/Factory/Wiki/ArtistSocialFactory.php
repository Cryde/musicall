<?php

namespace App\Tests\Factory\Wiki;

use App\Entity\Wiki\ArtistSocial;
use App\Repository\Wiki\ArtistSocialRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ArtistSocial>
 *
 * @method        ArtistSocial|Proxy create(array|callable $attributes = [])
 * @method static ArtistSocial|Proxy createOne(array $attributes = [])
 * @method static ArtistSocial|Proxy find(object|array|mixed $criteria)
 * @method static ArtistSocial|Proxy findOrCreate(array $attributes)
 * @method static ArtistSocial|Proxy first(string $sortedField = 'id')
 * @method static ArtistSocial|Proxy last(string $sortedField = 'id')
 * @method static ArtistSocial|Proxy random(array $attributes = [])
 * @method static ArtistSocial|Proxy randomOrCreate(array $attributes = [])
 * @method static ArtistSocialRepository|RepositoryProxy repository()
 * @method static ArtistSocial[]|Proxy[] all()
 * @method static ArtistSocial[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ArtistSocial[]|Proxy[] createSequence(array|callable $sequence)
 * @method static ArtistSocial[]|Proxy[] findBy(array $attributes)
 * @method static ArtistSocial[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ArtistSocial[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ArtistSocial> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ArtistSocial> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ArtistSocial> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ArtistSocial> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ArtistSocial> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ArtistSocial> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ArtistSocial> random(array $attributes = [])
 * @phpstan-method static Proxy<ArtistSocial> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ArtistSocialRepository> repository()
 * @phpstan-method static list<Proxy<ArtistSocial>> all()
 * @phpstan-method static list<Proxy<ArtistSocial>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ArtistSocial>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<ArtistSocial>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ArtistSocial>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ArtistSocial>> randomSet(int $number, array $attributes = [])
 */
final class ArtistSocialFactory extends ModelFactory
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
            'artist' => ArtistFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
            'type' => self::faker()->numberBetween(1, 32767),
            'url' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ArtistSocial $artistSocial): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ArtistSocial::class;
    }
}

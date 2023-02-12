<?php

namespace App\Tests\Factory\User;

use App\Entity\Image\UserProfilePicture;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<UserProfilePicture>
 *
 * @method        UserProfilePicture|Proxy create(array|callable $attributes = [])
 * @method static UserProfilePicture|Proxy createOne(array $attributes = [])
 * @method static UserProfilePicture|Proxy find(object|array|mixed $criteria)
 * @method static UserProfilePicture|Proxy findOrCreate(array $attributes)
 * @method static UserProfilePicture|Proxy first(string $sortedField = 'id')
 * @method static UserProfilePicture|Proxy last(string $sortedField = 'id')
 * @method static UserProfilePicture|Proxy random(array $attributes = [])
 * @method static UserProfilePicture|Proxy randomOrCreate(array $attributes = [])
 * @method static UserProfilePicture[]|Proxy[] all()
 * @method static UserProfilePicture[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UserProfilePicture[]|Proxy[] createSequence(array|callable $sequence)
 * @method static UserProfilePicture[]|Proxy[] findBy(array $attributes)
 * @method static UserProfilePicture[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserProfilePicture[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<UserProfilePicture> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<UserProfilePicture> createOne(array $attributes = [])
 * @phpstan-method static Proxy<UserProfilePicture> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<UserProfilePicture> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<UserProfilePicture> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<UserProfilePicture> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<UserProfilePicture> random(array $attributes = [])
 * @phpstan-method static Proxy<UserProfilePicture> randomOrCreate(array $attributes = [])
 * @phpstan-method static list<Proxy<UserProfilePicture>> all()
 * @phpstan-method static list<Proxy<UserProfilePicture>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<UserProfilePicture>> createSequence(array|callable $sequence)
 * @phpstan-method static list<Proxy<UserProfilePicture>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<UserProfilePicture>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<UserProfilePicture>> randomSet(int $number, array $attributes = [])
 */
final class UserProfilePictureFactory extends ModelFactory
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
            // ->afterInstantiate(function(UserProfilePicture $userProfilePicture): void {})
        ;
    }

    protected static function getClass(): string
    {
        return UserProfilePicture::class;
    }
}

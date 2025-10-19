<?php declare(strict_types=1);

namespace App\Fixtures\Factory\User;

use App\Entity\User;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    const string DEFAULT_PASSWORD = '$2y$04$v1LqXePkM/bTdPJSmZnbNuNM3ogkQoUJvQpVvoxT7VF1PItj1c8HO'; // it's 'password'

    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'email' => self::faker()->email(),
            'lastLoginDatetime' => null,
            'password' => self::DEFAULT_PASSWORD,
            'roles' => [],
            'username' => self::faker()->userName(),
        ];
    }

    public function asAdminUser(): static
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'email' => 'admin@email.com',
            'password' => self::DEFAULT_PASSWORD,
            'roles' => ['ROLE_ADMIN'],
            'username' => 'user_admin',
        ]);
    }

    public function asBaseUser(): static
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'email' => 'base_user@email.com',
            'password' => self::DEFAULT_PASSWORD,
            'roles' => [],
            'username' => 'base_user',
        ]);
    }

    public static function class(): string
    {
        return User::class;
    }
}

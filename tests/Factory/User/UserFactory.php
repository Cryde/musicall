<?php

namespace App\Tests\Factory\User;

use App\Entity\User;
use App\Entity\User\UserProfile;
use DateTime;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    const string DEFAULT_PASSWORD = '$2y$04$v1LqXePkM/bTdPJSmZnbNuNM3ogkQoUJvQpVvoxT7VF1PItj1c8HO'; // it's 'password'

    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'email' => self::faker()->text(180),
            'lastLoginDatetime' => new DateTime(),
            'password' => self::faker()->text(),
            'roles' => [],
            'username' => self::faker()->userName(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            if ($user->getProfile() === null) {
                $profile = new UserProfile();
                $profile->setUser($user);
                $profile->setCreationDatetime($user->getCreationDatetime()
                    ? \DateTimeImmutable::createFromMutable($user->getCreationDatetime())
                    : new \DateTimeImmutable()
                );
                $user->setProfile($profile);
            }
        });
    }

    public function asAdminUser(): static
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'email' => 'admin@email.com',
            'password' => self::DEFAULT_PASSWORD,
            'roles' => ['ROLE_ADMIN'],
            'username' => 'user_admin',
            'confirmationDatetime' => new DateTime('1990-01-02T02:03:04+00:00')
        ]);
    }

    public function asBaseUser(): static
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'email' => 'base_user@email.com',
            'password' => self::DEFAULT_PASSWORD,
            'roles' => [],
            'username' => 'base_admin',
            'confirmationDatetime' => new DateTime('1990-01-02T02:03:04+00:00')
        ]);
    }

    public static function class(): string
    {
        return User::class;
    }
}

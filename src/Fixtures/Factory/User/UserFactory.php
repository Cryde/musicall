<?php declare(strict_types=1);

namespace App\Fixtures\Factory\User;

use App\Entity\User;
use App\Entity\User\UserProfile;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
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
            'confirmationDatetime' => self::faker()->dateTime('-1 year'),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $reflection = new \ReflectionProperty($user, 'profile');
            if (!$reflection->isInitialized($user)) {
                $profile = new UserProfile();
                $profile->creationDatetime = \DateTimeImmutable::createFromMutable($user->creationDatetime);
                $user->profile = $profile;
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
        ]);
    }

    public function asBaseUser(): static
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'email' => 'user_base@email.com',
            'password' => self::DEFAULT_PASSWORD,
            'roles' => [],
            'username' => 'user_base',
        ]);
    }

    /**
     * ROLE_TESTER reveals features that are merged but not yet announced, so dev needs an account
     * that can actually reach them.
     *
     * Deliberately an ordinary user carrying nothing else. That is the whole point of the role
     * (#942): it is a feature flag rather than a rank, so the fixtures have to prove a non admin
     * reaches the module, and that user_admin does not reach it by virtue of being an admin.
     */
    public function asTesterUser(): static
    {
        return $this->with([
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '1990-01-02T02:03:04+00:00'),
            'email' => 'user_tester@email.com',
            'password' => self::DEFAULT_PASSWORD,
            'roles' => ['ROLE_TESTER'],
            'username' => 'user_tester',
        ]);
    }

    public static function class(): string
    {
        return User::class;
    }
}

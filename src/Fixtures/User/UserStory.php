<?php declare(strict_types=1);

namespace App\Fixtures\User;

use App\Fixtures\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
#[AsFixture(name: 'user')]
class UserStory extends Story
{
    const string ADMIN_USER = 'admin_user';
    const string BASE_USER = 'base_user';
    const string SUPER_ADMIN_USER = 'super_admin_user';
    const string POOL_USERS = 'pool_user';

    public function build(): void
    {
        $this->addState(self::ADMIN_USER, UserFactory::new()->asAdminUser());
        $this->addState(self::BASE_USER, UserFactory::new()->asBaseUser());
        $this->addState(self::SUPER_ADMIN_USER, UserFactory::new()->asSuperAdminUser());
        $this->addToPool(self::POOL_USERS, UserFactory::new()->many(20));
    }
}

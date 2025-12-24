<?php declare(strict_types=1);

namespace App\Fixtures\BandSpace;

use App\Enum\BandSpace\Role;
use App\Fixtures\Factory\BandSpace\BandSpaceFactory;
use App\Fixtures\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
#[AsFixture(name: 'band_space')]
class BandSpaceStory extends Story
{
    const string ADMIN_BAND = 'admin_band';
    const string BASE_USER_BAND = 'base_user_band';
    const string COLLABORATIVE_BAND = 'collaborative_band';

    public function build(): void
    {
        $adminUser = UserStory::get(UserStory::ADMIN_USER);
        $baseUser = UserStory::get(UserStory::BASE_USER);

        // Admin's band space (admin is creator)
        $adminBand = BandSpaceFactory::new()
            ->with(['name' => 'Admin\'s Rock Band'])
            ->create();
        BandSpaceMembershipFactory::new()
            ->with([
                'bandSpace' => $adminBand,
                'user' => $adminUser,
                'role' => Role::Admin
            ])
            ->create();
        $this->addState(self::ADMIN_BAND, $adminBand);

        // Base user's band space (base user is creator)
        $baseUserBand = BandSpaceFactory::new()
            ->with(['name' => 'The Jazz Collective'])
            ->create();
        BandSpaceMembershipFactory::new()
            ->with([
                'bandSpace' => $baseUserBand,
                'user' => $baseUser,
                'role' => Role::Admin
            ])
            ->create();
        $this->addState(self::BASE_USER_BAND, $baseUserBand);

        // Collaborative band with multiple members
        $collaborativeBand = BandSpaceFactory::new()
            ->with(['name' => 'The Collaborators'])
            ->create();

        // Admin is creator
        BandSpaceMembershipFactory::new()
            ->with([
                'bandSpace' => $collaborativeBand,
                'user' => $adminUser,
                'role' => Role::Admin
            ])
            ->create();

        // Base user is a member
        BandSpaceMembershipFactory::new()
            ->with([
                'bandSpace' => $collaborativeBand,
                'user' => $baseUser,
                'role' => Role::User
            ])
            ->create();

        for ($i = 0; $i < 3; $i++) {
            BandSpaceMembershipFactory::new()
                ->with([
                    'bandSpace' => $collaborativeBand,
                    'user' => UserStory::getRandom(UserStory::POOL_USERS),
                    'role' => Role::User
                ])
                ->create();
        }

        $this->addState(self::COLLABORATIVE_BAND, $collaborativeBand);
    }
}

<?php

declare(strict_types=1);

namespace App\Fixtures\Musician;

use App\Fixtures\Attribute\InstrumentStory;
use App\Fixtures\Attribute\StyleStory;
use App\Fixtures\Factory\Musician\MusicianProfileFactory;
use App\Fixtures\Factory\Musician\MusicianProfileInstrumentFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
#[AsFixture(name: 'musician_profile')]
class MusicianProfileStory extends Story
{
    public function build(): void
    {
        // Get some users from the pool (8 out of 20)
        $users = UserStory::getRandomRange(UserStory::POOL_USERS, 8, 8);

        foreach ($users as $user) {
            // Get random styles for this profile
            $styles = StyleStory::getRandomRange(StyleStory::ATTRIBUTES_STYLES, 1, 4);
            $styleEntities = array_map(fn($style) => $style->_real(), $styles);

            // Create musician profile for the user with styles
            $musicianProfile = MusicianProfileFactory::new()->create([
                'user' => $user,
                'styles' => $styleEntities,
            ]);

            // Add 1-3 instruments to the profile
            $instruments = InstrumentStory::getRandomRange(
                InstrumentStory::ATTRIBUTES_INSTRUMENTS,
                1,
                3
            );

            foreach ($instruments as $instrument) {
                MusicianProfileInstrumentFactory::new()->create([
                    'musicianProfile' => $musicianProfile,
                    'instrument' => $instrument,
                ]);
            }
        }
    }
}

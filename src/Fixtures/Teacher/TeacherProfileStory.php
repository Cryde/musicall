<?php

declare(strict_types=1);

namespace App\Fixtures\Teacher;

use App\Fixtures\Attribute\InstrumentStory;
use App\Fixtures\Attribute\StyleStory;
use App\Fixtures\Factory\Teacher\TeacherProfileFactory;
use App\Fixtures\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
#[AsFixture(name: 'teacher_profile')]
class TeacherProfileStory extends Story
{
    public function build(): void
    {
        // Get some users from the pool (6 out of 20)
        $users = UserStory::getRandomRange(UserStory::POOL_USERS, 6, 6);

        foreach ($users as $user) {
            // Get random styles for this profile
            $styles = StyleStory::getRandomRange(StyleStory::ATTRIBUTES_STYLES, 1, 3);
            $styleEntities = array_map(fn($style) => $style->_real(), $styles);

            // Create teacher profile for the user with styles
            $teacherProfile = TeacherProfileFactory::new()->create([
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
                TeacherProfileInstrumentFactory::new()->create([
                    'teacherProfile' => $teacherProfile,
                    'instrument' => $instrument,
                ]);
            }
        }
    }
}

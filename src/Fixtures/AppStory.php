<?php declare(strict_types=1);

namespace App\Fixtures;

use App\Fixtures\Announce\MusicianAnnounceStory;
use App\Fixtures\Attribute\InstrumentStory;
use App\Fixtures\Attribute\StyleStory;
use App\Fixtures\BandSpace\BandSpaceStory;
use App\Fixtures\Course\CourseCategoryStory;
use App\Fixtures\Course\CourseStory;
use App\Fixtures\Course\FakeCourseImageGeneratorStory;
use App\Fixtures\Forum\ForumStory;
use App\Fixtures\Musician\MusicianProfileStory;
use App\Fixtures\Publication\FakeImageGeneratorStory;
use App\Fixtures\Publication\PublicationCategoryStory;
use App\Fixtures\Publication\PublicationStory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
#[AsFixture(name: 'app')]
final class AppStory extends Story
{
    public function build(): void
    {
        // create users
        UserStory::load();

        // handle publications
        FakeImageGeneratorStory::load();
        PublicationCategoryStory::load();
        PublicationStory::load();

        // courses
        FakeCourseImageGeneratorStory::load();
        CourseCategoryStory::load();
        CourseStory::load();

        // musician announces
        InstrumentStory::load();
        StyleStory::load();
        MusicianAnnounceStory::load();

        // musician profiles
        MusicianProfileStory::load();

        // band spaces
        BandSpaceStory::load();

        // forums
        ForumStory::load();
    }
}

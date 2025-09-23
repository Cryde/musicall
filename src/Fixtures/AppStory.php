<?php

namespace App\Fixtures;

use App\Fixtures\Publication\FakeImageGeneratorStory;
use App\Fixtures\Publication\PublicationCategoryStory;
use App\Fixtures\Publication\PublicationStory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

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
    }
}

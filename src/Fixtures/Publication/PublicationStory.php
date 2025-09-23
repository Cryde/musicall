<?php

namespace App\Fixtures\Publication;

use App\Fixtures\Factory\Publication\PublicationFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'publications')]
class PublicationStory extends Story
{
    public function build(): void
    {
            PublicationFactory::new()
                ->with(
                    static fn() => [
                        'subCategory' => PublicationCategoryStory::getRandom(PublicationCategoryStory::WRITEABLE_CATEGORIES),
                        'author' => UserStory::getRandom(UserStory::POOL_USERS)
                    ]
                )
                ->many(200)
                ->applyStateMethod('asBaseTextPublicationOnline')->create();
    }
}

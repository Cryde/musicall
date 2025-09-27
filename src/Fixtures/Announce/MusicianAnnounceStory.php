<?php

namespace App\Fixtures\Announce;

use App\Fixtures\Attribute\InstrumentStory;
use App\Fixtures\Attribute\StyleStory;
use App\Fixtures\Factory\Announce\MusicianAnnounceFactory;
use App\Fixtures\User\UserStory;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class MusicianAnnounceStory extends Story
{
    public function build(): void
    {
        MusicianAnnounceFactory::new()
            ->with(
                static function () {
                    return [
                        'instrument' => InstrumentStory::getRandom(InstrumentStory::ATTRIBUTES_INSTRUMENTS),
                        'styles'     => StyleStory::getRandomRange(StyleStory::ATTRIBUTES_STYLES, 1, 5),
                        'author'     => UserStory::getRandom(UserStory::POOL_USERS),
                    ];
                }
            )
            ->many(100)
            ->create();
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Integration\Repository\BandSpace;

use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\SetlistRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistRepositoryDurationStatsTest extends KernelTestCase
{
    public function test_empty_setlist_returns_zero_total_and_zero_missing(): void
    {
        $repo = static::getContainer()->get(SetlistRepository::class);
        $band = BandSpaceFactory::createOne();
        $setlist = SetlistFactory::createOne(['bandSpace' => $band]);

        $stats = $repo->durationStats($setlist);

        $this->assertSame(['total' => 0, 'missing' => 0], $stats);
    }

    public function test_sums_override_then_reference_then_skips_missing(): void
    {
        $repo = static::getContainer()->get(SetlistRepository::class);
        $band = BandSpaceFactory::createOne();
        $setlist = SetlistFactory::createOne(['bandSpace' => $band]);
        $songWithRef = SongFactory::createOne(['bandSpace' => $band, 'referenceDuration' => 100]);
        $songNoRef = SongFactory::createOne(['bandSpace' => $band, 'referenceDuration' => null]);

        SetlistItemFactory::createOne([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $songWithRef,
            'durationOverride' => 60,
            'position' => 0,
        ]);
        SetlistItemFactory::createOne([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $songWithRef,
            'durationOverride' => null,
            'position' => 1,
        ]);
        SetlistItemFactory::createOne([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'MC',
            'durationOverride' => 30,
            'position' => 2,
        ]);
        SetlistItemFactory::createOne([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $songNoRef,
            'durationOverride' => null,
            'position' => 3,
        ]);
        SetlistItemFactory::createOne([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'No-duration talk',
            'durationOverride' => null,
            'position' => 4,
        ]);

        $stats = $repo->durationStats($setlist);

        $this->assertSame(['total' => 190, 'missing' => 2], $stats);
    }
}

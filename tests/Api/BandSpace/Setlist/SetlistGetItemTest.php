<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\SetlistItemType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_setlist_with_mixed_items_and_total_duration(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Live set',
            'creationDatetime' => new \DateTime('2026-05-15T10:00:00+00:00'),
        ])->create();

        // Song with refDur=180, no override -> contributes 180
        $songA = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Song A',
            'referenceDuration' => 180,
        ])->create();
        $itemA = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $songA,
            'label' => null,
            'position' => 0,
        ])->create();

        // Archived song with refDur=240, override=200 -> contributes 200
        $songB = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Song B (archived)',
            'referenceDuration' => 240,
            'archiveDatetime' => new \DateTimeImmutable('2026-05-10T10:00:00+00:00'),
        ])->create();
        $itemB = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $songB,
            'label' => null,
            'durationOverride' => 200,
            'position' => 1,
        ])->create();

        // Talk with override=30 -> contributes 30
        $itemC = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'Band intro',
            'durationOverride' => 30,
            'position' => 2,
        ])->create();

        // Break without override -> contributes 0
        $itemD = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Break,
            'label' => 'Pause',
            'position' => 3,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id,
            '@type' => 'Setlist',
            'id' => $setlist->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Live set',
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemA->id,
                    '@type' => 'SetlistItem',
                    'id' => $itemA->id,
                    'band_space_id' => $bandSpace->id,
                    'setlist_id' => $setlist->id,
                    'type' => 'song',
                    'song' => [
                        'id' => $songA->id,
                        'title' => 'Song A',
                        'tempo' => null,
                        'tonality' => null,
                        'reference_duration' => 180,
                        'archive_datetime' => null,
                        '@type' => 'SetlistItemSongInfo',
                    ],
                    'label' => null,
                    'duration_override' => null,
                    'note' => null,
                    'transition' => null,
                    'position' => 0,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemB->id,
                    '@type' => 'SetlistItem',
                    'id' => $itemB->id,
                    'band_space_id' => $bandSpace->id,
                    'setlist_id' => $setlist->id,
                    'type' => 'song',
                    'song' => [
                        'id' => $songB->id,
                        'title' => 'Song B (archived)',
                        'tempo' => null,
                        'tonality' => null,
                        'reference_duration' => 240,
                        'archive_datetime' => $songB->archiveDatetime->format(\DateTimeInterface::ATOM),
                        '@type' => 'SetlistItemSongInfo',
                    ],
                    'label' => null,
                    'duration_override' => 200,
                    'note' => null,
                    'transition' => null,
                    'position' => 1,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemC->id,
                    '@type' => 'SetlistItem',
                    'id' => $itemC->id,
                    'band_space_id' => $bandSpace->id,
                    'setlist_id' => $setlist->id,
                    'type' => 'talk',
                    'song' => null,
                    'label' => 'Band intro',
                    'duration_override' => 30,
                    'note' => null,
                    'transition' => null,
                    'position' => 2,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemD->id,
                    '@type' => 'SetlistItem',
                    'id' => $itemD->id,
                    'band_space_id' => $bandSpace->id,
                    'setlist_id' => $setlist->id,
                    'type' => 'break',
                    'song' => null,
                    'label' => 'Pause',
                    'duration_override' => null,
                    'note' => null,
                    'transition' => null,
                    'position' => 3,
                ],
            ],
            'total_duration_seconds' => 410,
        ]);
    }

    public function test_get_setlist_cross_band_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBand, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/setlists/' . $setlist->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Setlist introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Setlist introuvable',
        ]);
    }

    public function test_get_setlist_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }
}

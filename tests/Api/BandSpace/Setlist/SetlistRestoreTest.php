<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Setlist;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\SetlistWriteGuard;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Archiving a setlist used to be one way: nothing listed the archived row and nothing cleared the
 * flag, so an accidental delete was invisible, and since #824 made archived setlists read only it
 * was frozen as well. Duplicating was the only escape, and it produces a copy rather than the
 * original back.
 */
#[ResetDatabase]
class SetlistRestoreTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /**
     * The membership role is pinned to User rather than left to the factory default, because the rule
     * under test is that restoring is NOT admin gated: any member may archive a setlist, so any member
     * brings it back. A setlist has no creator to prefer over the rest of the band.
     */
    public function test_restore_takes_the_setlist_out_of_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'role' => Role::User])->create();

        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $setlistId = (string) $setlist->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId,
            '@type' => 'Setlist',
            'id' => $setlistId,
            'band_space_id' => $bandSpace->id,
            'name' => 'Concert 12/06',
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => [],
            'total_duration_seconds' => 0,
        ]);

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Setlist, $setlistId);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_unarchived', $activities[0]->type);
        $this->assertSame(['name' => 'Concert 12/06'], $activities[0]->payload);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistRepository::class)->find($setlistId);
        $this->assertInstanceOf(Setlist::class, $stored);
        $this->assertNull($stored->archiveDatetime);

        // The other half of #824: the guard that froze this row has to let go once it is restored,
        // otherwise the setlist comes back visible but still unwritable. Instantiated rather than
        // pulled from the container because it has no dependencies and this is the real class.
        (new SetlistWriteGuard())->assertWritable($stored);
        $this->addToAssertionCount(1);
    }

    /**
     * The restored setlist is back in the live collection, which is the list the sidebar renders and
     * the one an archived row was missing from.
     */
    public function test_a_restored_setlist_is_listed_again(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/setlists/' . $setlist->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(BandSpaceRepository::class)->find($bandSpaceId);
        $setlistRepository = self::getContainer()->get(SetlistRepository::class);

        $live = $setlistRepository->findByBandSpace($reloadedBand);
        $this->assertCount(1, $live);
        $this->assertSame('Concert 12/06', $live[0]->name);

        $this->assertCount(0, $setlistRepository->findByBandSpace($reloadedBand, true), 'The trash is empty again');
    }

    /**
     * A setlist item points at a Song, and a song archived separately keeps rendering in the items that
     * already reference it, because SetlistRepository::findOneByIdAndBandSpace does not filter archived
     * songs. So restoring a setlist gives the whole programme back, durations included, and leaves the
     * song archived: un-archiving a title the band retired on purpose is a repertoire decision, not a
     * side effect. The title stays out of the repertoire and out of new setlists until it is restored
     * in its own right.
     */
    public function test_restore_keeps_an_item_pointing_at_an_archived_song(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $archivedSong = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Trop tard',
            'referenceDuration' => 210,
            'tempo' => 128,
            'tonality' => 'Am',
            'creationDatetime' => new DateTime('2026-01-05T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-06-10T09:00:00+00:00'),
        ])->create();
        $archivedSongId = (string) $archivedSong->id;

        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $archivedSong,
            'label' => null,
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id,
            '@type' => 'Setlist',
            'id' => $setlist->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Concert 12/06',
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $item->id,
                    '@type' => 'SetlistItem',
                    'id' => $item->id,
                    'band_space_id' => $bandSpace->id,
                    'setlist_id' => $setlist->id,
                    'type' => 'song',
                    'song' => [
                        'id' => $archivedSongId,
                        'title' => 'Trop tard',
                        'tempo' => 128,
                        'tonality' => 'Am',
                        'reference_duration' => 210,
                        'archive_datetime' => $archivedSong->archiveDatetime->format(DateTimeInterface::ATOM),
                        '@type' => 'SetlistItemSongInfo',
                    ],
                    'label' => null,
                    'duration_override' => null,
                    'note' => null,
                    'transition' => null,
                    'position' => 0,
                ],
            ],
            'total_duration_seconds' => 210,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $storedSong = self::getContainer()->get(\App\Repository\BandSpace\SongRepository::class)->find($archivedSongId);
        $this->assertNotNull($storedSong?->archiveDatetime, 'Restoring a setlist does not un-archive its songs');
    }

    public function test_restore_a_live_setlist_conflicts(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Still live',
            'creationDatetime' => new DateTime('2026-06-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Cette setlist n'est pas dans la corbeille",
            'status' => 409,
            'type' => '/errors/409',
            'description' => "Cette setlist n'est pas dans la corbeille",
        ]);
    }

    public function test_restore_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $setlistId = (string) $setlist->id;

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId . '/restore',
            [],
            self::HEADERS,
        );

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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistRepository::class)->find($setlistId);
        $this->assertNotNull($stored?->archiveDatetime);
    }

    /**
     * The band space in the path is the one the setlist is looked up in, so a member of A cannot reach
     * into B's trash by pointing his own space at their setlist id.
     */
    public function test_restore_a_setlist_of_another_band_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $theirSetlist = $this->archivedSetlist($otherBand, 'Their list');
        $theirSetlistId = (string) $theirSetlist->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $myBand->id . '/setlists/' . $theirSetlistId . '/restore',
            [],
            self::HEADERS,
        );

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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistRepository::class)->find($theirSetlistId);
        $this->assertNotNull($stored?->archiveDatetime);
    }

    public function test_restore_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');

        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    private function archivedSetlist(BandSpace $bandSpace, string $name): Setlist
    {
        return SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => $name,
            'creationDatetime' => new DateTime('2026-06-01T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-06-13T09:00:00+00:00'),
        ])->create();
    }
}

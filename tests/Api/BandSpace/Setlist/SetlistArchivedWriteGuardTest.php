<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Setlist;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Repository\BandSpace\SetlistRepository;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Archiving a setlist used to hide it and nothing more: the finder behind every write returns
 * archived rows on purpose, so a member who still had the list open, or who came back through a
 * bookmarked ?setlist=<id>, could rename it and rework its programme, every request answered with
 * a 200, into a row that stays invisible until somebody thinks to restore it.
 *
 * Reads have to keep working, so each refusal is paired with proof the row is still readable and
 * still duplicable. Restore itself is exempt by construction and is covered in SetlistRestoreTest.
 */
#[ResetDatabase]
class SetlistArchivedWriteGuardTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const array PATCH_HEADERS = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private const string ARCHIVED_DETAIL = 'Cette setlist est archivée, les modifications sont désactivées';

    public function test_renaming_an_archived_setlist_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $setlistId = (string) $setlist->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId,
            ['name' => 'Concert 19/06'],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => self::ARCHIVED_DETAIL,
            'status' => 409,
            'type' => '/errors/409',
            'description' => self::ARCHIVED_DETAIL,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistRepository::class)->find($setlistId);
        $this->assertSame('Concert 12/06', $stored?->name);
    }

    public function test_adding_an_item_to_an_archived_setlist_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $setlistId = (string) $setlist->id;
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Trop tard'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId . '/items',
            ['type' => 'song', 'song_id' => (string) $song->id],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => self::ARCHIVED_DETAIL,
            'status' => 409,
            'type' => '/errors/409',
            'description' => self::ARCHIVED_DETAIL,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistRepository::class)->find($setlistId);
        $this->assertCount(0, $stored->items);
    }

    public function test_editing_an_item_of_an_archived_setlist_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'Présentation du groupe',
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            ['label' => 'Modifié malgré tout'],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => self::ARCHIVED_DETAIL,
            'status' => 409,
            'type' => '/errors/409',
            'description' => self::ARCHIVED_DETAIL,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SetlistItemRepository::class)->find($itemId);
        $this->assertSame('Présentation du groupe', $stored?->label);
    }

    public function test_removing_an_item_of_an_archived_setlist_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'Présentation du groupe',
            'position' => 0,
        ])->create();
        $itemId = (string) $item->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemId,
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => self::ARCHIVED_DETAIL,
            'status' => 409,
            'type' => '/errors/409',
            'description' => self::ARCHIVED_DETAIL,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(self::getContainer()->get(SetlistItemRepository::class)->find($itemId));
    }

    public function test_reordering_an_archived_setlist_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        $first = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Un', 'position' => 0])->create();
        $second = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Deux', 'position' => 1])->create();
        $firstId = (string) $first->id;
        $secondId = (string) $second->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/reorder',
            ['positions' => [
                ['id' => $secondId, 'position' => 0],
                ['id' => $firstId, 'position' => 1],
            ]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => self::ARCHIVED_DETAIL,
            'status' => 409,
            'type' => '/errors/409',
            'description' => self::ARCHIVED_DETAIL,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $itemRepository = self::getContainer()->get(SetlistItemRepository::class);
        $this->assertSame(0, $itemRepository->find($firstId)?->position);
        $this->assertSame(1, $itemRepository->find($secondId)?->position);
    }

    /**
     * The finder is shared with every write path, so the guard had to be put on the writes rather
     * than in the query. This is the half that must NOT change: an archived setlist is still read,
     * because that is how last year's PDF gets exported.
     */
    public function test_an_archived_setlist_is_still_readable(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Concert 12/06',
            'creationDatetime' => new DateTime('2026-06-01T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-06-13T09:00:00+00:00'),
        ])->create();
        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Break,
            'label' => 'Pause',
            'durationOverride' => 300,
            'position' => 0,
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
            'name' => 'Concert 12/06',
            'archive_datetime' => $setlist->archiveDatetime->format(\DateTimeInterface::ATOM),
            'creation_datetime' => $setlist->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $item->id,
                    '@type' => 'SetlistItem',
                    'id' => $item->id,
                    'band_space_id' => $bandSpace->id,
                    'setlist_id' => $setlist->id,
                    'type' => 'break',
                    'song' => null,
                    'label' => 'Pause',
                    'duration_override' => 300,
                    'note' => null,
                    'transition' => null,
                    'position' => 0,
                ],
            ],
            'total_duration_seconds' => 300,
        ]);
    }

    /**
     * Duplicating reads the archived list without touching it, so the guard must never catch it.
     * Restore (#761) is now how the original comes back; duplicating serves the other case, building
     * this year's list from last year's while the archived one stays archived.
     */
    public function test_an_archived_setlist_can_still_be_duplicated(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = $this->archivedSetlist($bandSpace, 'Concert 12/06');
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'Présentation du groupe',
            'position' => 0,
        ])->create();
        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/setlists/' . $setlist->id . '/duplicate',
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find($bandSpaceId);
        $live = self::getContainer()->get(SetlistRepository::class)->findByBandSpace($reloadedBand);
        $this->assertCount(1, $live, 'The copy is a live setlist, the archived source stays hidden');
        $this->assertSame('Concert 12/06 (copie)', $live[0]->name);
        $this->assertCount(1, $live[0]->items);
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

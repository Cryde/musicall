<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistDuplicateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_duplicate_creates_copy_with_items(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $source = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Original'])->create();
        $sourceId = (string) $source->id;

        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Shared song'])->create();
        $songId = (string) $song->id;

        SetlistItemFactory::new([
            'setlist' => $source,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'label' => null,
            'position' => 0,
        ])->create();
        SetlistItemFactory::new([
            'setlist' => $source,
            'type' => SetlistItemType::Talk,
            'label' => 'Outro',
            'position' => 1,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $sourceId . '/duplicate',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $bandSpaceId = (string) $bandSpace->id;
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find($bandSpaceId);
        $setlists = self::getContainer()->get(SetlistRepository::class)->findByBandSpace($reloadedBand);
        $this->assertCount(2, $setlists);
        $copy = null;
        foreach ($setlists as $candidate) {
            if ((string) $candidate->id !== $sourceId) {
                $copy = $candidate;
                break;
            }
        }
        $this->assertNotNull($copy);
        $this->assertSame('Original (copie)', $copy->name);
        $this->assertCount(2, $copy->items);

        // Items keep the same song refs + positions, but have new UUIDs.
        $items = $copy->items->toArray();
        usort($items, fn ($a, $b) => $a->position <=> $b->position);
        $this->assertSame(SetlistItemType::Song, $items[0]->type);
        $this->assertNotNull($items[0]->song);
        $this->assertSame($songId, (string) $items[0]->song->id);
        $this->assertSame(0, $items[0]->position);
        $this->assertSame(SetlistItemType::Talk, $items[1]->type);
        $this->assertNull($items[1]->song);
        $this->assertSame('Outro', $items[1]->label);
        $this->assertSame(1, $items[1]->position);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Setlist, (string) $copy->id);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_duplicated', $activities[0]->type);
    }

    /**
     * A duplicate exists to build the next gig from the last one, and the lyric sheets and backing
     * tracks are half of what makes the list usable. Attachments are keyed by (sourceType,
     * sourceId), so nothing follows the copy on its own and they used to vanish from it silently.
     * Only the attachment rows are cloned, the files themselves stay shared.
     */
    public function test_duplicate_carries_the_file_attachments_over(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $source = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Original'])->create();
        $sourceId = (string) $source->id;

        $lyrics = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'originalName' => 'paroles.pdf'])
            ->withAttachment(['type' => 'setlist', 'id' => $sourceId])
            ->create();
        $backingTrack = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'originalName' => 'playback.mp3'])
            ->withAttachment(['type' => 'setlist', 'id' => $sourceId])
            ->create();

        // Attached to another setlist, so it must not follow this copy.
        $otherSetlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Autre'])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'originalName' => 'ailleurs.pdf'])
            ->withAttachment(['type' => 'setlist', 'id' => (string) $otherSetlist->id])
            ->create();

        // Trashed file: the copy has no reason to carry a document nobody can open any more.
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'originalName' => 'corbeille.pdf',
            'archiveDatetime' => new \DateTimeImmutable('2026-06-01T10:00:00+00:00'),
        ])
            ->withAttachment(['type' => 'setlist', 'id' => $sourceId])
            ->create();

        $lyricsId = (string) $lyrics->id;
        $backingTrackId = (string) $backingTrack->id;
        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/setlists/' . $sourceId . '/duplicate',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $copyId = $this->getResponseAsArray()['id'];
        $this->assertNotSame($sourceId, $copyId);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $attachmentRepository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);

        $copiedFileIds = array_map(
            static fn ($attachment): string => (string) $attachment->bandSpaceFile->id,
            $attachmentRepository->findActiveBySource('setlist', $copyId),
        );
        sort($copiedFileIds);
        $expectedFileIds = [$lyricsId, $backingTrackId];
        sort($expectedFileIds);
        $this->assertSame($expectedFileIds, $copiedFileIds);

        // The source keeps its own rows: the copy points at the same files, it does not steal them.
        $this->assertCount(3, $attachmentRepository->findBySource('setlist', [$sourceId]));
    }

    /**
     * The other side of refusing an archived song at item creation: a band archives a song, then
     * duplicates last season's list to build the next one. The copy assigns the song reference
     * straight across instead of going through the create path, and it has to stay that way, or
     * archiving one song would quietly cost the band every setlist that still contains it.
     */
    public function test_duplicate_keeps_an_item_pointing_at_an_archived_song(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $source = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Saison passée'])->create();
        $sourceId = (string) $source->id;

        $archivedSong = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Retirée du répertoire',
            'archiveDatetime' => new \DateTimeImmutable('2026-06-13T09:00:00+00:00'),
        ])->create();
        $archivedSongId = (string) $archivedSong->id;

        SetlistItemFactory::new([
            'setlist' => $source,
            'type' => SetlistItemType::Song,
            'song' => $archivedSong,
            'label' => null,
            'position' => 0,
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/setlists/' . $sourceId . '/duplicate',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $copyId = $this->getResponseAsArray()['id'];

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $copy = self::getContainer()->get(SetlistRepository::class)->find($copyId);
        $this->assertCount(1, $copy->items);
        $this->assertSame($archivedSongId, (string) $copy->items->first()->song->id);
    }

    public function test_duplicate_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/duplicate',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Several songs added in one request (#1062).
 */
#[ResetDatabase]
class SetlistItemsBulkCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_songs_are_added_in_the_order_given_and_answered_with_the_setlist(): void
    {
        [$user, $space, $setlist] = $this->setlist();
        $first = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Neon Tide', 'tempo' => 132, 'tonality' => 'Bm', 'referenceDuration' => 232]);
        $second = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Driftwood', 'tempo' => null, 'tonality' => null, 'referenceDuration' => null]);

        $this->client->loginUser($user);
        $this->bulk($space, $setlist, ['song_ids' => [(string) $first->id, (string) $second->id]]);

        $this->assertResponseIsSuccessful();
        $items = $this->items($setlist);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $space->id . '/setlists/' . $setlist->id,
            '@type' => 'Setlist',
            'id' => (string) $setlist->id,
            'band_space_id' => (string) $space->id,
            'name' => 'Tour 2026',
            'target_duration' => null,
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $items[0]->setlist->updateDatetime->format(\DateTimeInterface::ATOM),
            'items' => [
                $this->songItem($space, $setlist, $items[0], ['id' => (string) $first->id, 'title' => 'Neon Tide', 'tempo' => 132, 'tonality' => 'Bm', 'reference_duration' => 232], 0),
                $this->songItem($space, $setlist, $items[1], ['id' => (string) $second->id, 'title' => 'Driftwood', 'tempo' => null, 'tonality' => null, 'reference_duration' => null], 1),
            ],
            'total_duration_seconds' => 232,
        ]);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $setlist->id);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_items_added', $activities[0]->type);
        $this->assertSame(['count' => 2, 'name' => 'Tour 2026'], $activities[0]->payload);
    }

    public function test_songs_go_where_they_are_asked_and_the_same_song_twice_is_an_encore(): void
    {
        [$user, $space, $setlist] = $this->setlist();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Intro', 'position' => 0])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Merci', 'position' => 1])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Neon Tide']);

        $this->client->loginUser($user);
        $this->bulk($space, $setlist, ['song_ids' => [(string) $song->id, (string) $song->id], 'position' => 1]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [['Intro', 0], ['Neon Tide', 1], ['Neon Tide', 2], ['Merci', 3]],
            array_map(static fn (SetlistItem $item): array => [$item->label ?? $item->song?->title, $item->position], $this->items($setlist)),
        );
    }

    public function test_a_song_of_another_band_refuses_the_whole_request(): void
    {
        [$user, $space, $setlist] = $this->setlist();
        $mine = SongFactory::new()->create(['bandSpace' => $space]);
        $theirs = SongFactory::new()->create(['bandSpace' => BandSpaceFactory::new()->create()]);

        $this->client->loginUser($user);
        $this->bulk($space, $setlist, ['song_ids' => [(string) $mine->id, (string) $theirs->id]]);

        $this->assertNotInRepertoire();
        $this->assertSame([], $this->items($setlist));
    }

    public function test_an_archived_song_is_refused(): void
    {
        [$user, $space, $setlist] = $this->setlist();
        $archived = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new \DateTimeImmutable('2026-09-01')]);

        $this->client->loginUser($user);
        $this->bulk($space, $setlist, ['song_ids' => [(string) $archived->id]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette chanson est archivée, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cette chanson est archivée, les modifications sont désactivées',
        ]);
        $this->assertSame([], $this->items($setlist));
    }

    public function test_more_than_the_cap_is_refused(): void
    {
        [$user, $space, $setlist] = $this->setlist();
        $song = SongFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($user);
        $this->bulk($space, $setlist, ['song_ids' => array_fill(0, 201, (string) $song->id)]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'song_ids',
                    'message' => 'Pas plus de 200 titres à la fois',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
            ],
            'detail' => 'song_ids: Pas plus de 200 titres à la fois',
            'type' => '/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            'title' => 'An error occurred',
            'description' => 'song_ids: Pas plus de 200 titres à la fois',
        ]);
    }

    public function test_no_song_is_refused(): void
    {
        [$user, $space, $setlist] = $this->setlist();

        $this->client->loginUser($user);
        $this->bulk($space, $setlist, ['song_ids' => []]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'song_ids',
                    'message' => 'Choisissez au moins un titre',
                    'code' => 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
                ],
            ],
            'detail' => 'song_ids: Choisissez au moins un titre',
            'type' => '/validation_errors/bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            'title' => 'An error occurred',
            'description' => 'song_ids: Choisissez au moins un titre',
        ]);
    }

    public function test_an_archived_setlist_takes_no_songs(): void
    {
        [$user, $space] = $this->setlist();
        $archived = SetlistFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new \DateTimeImmutable('2026-09-01')]);
        $song = SongFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($user);
        $this->bulk($space, $archived, ['song_ids' => [(string) $song->id]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette setlist est archivée, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cette setlist est archivée, les modifications sont désactivées',
        ]);
    }

    public function test_a_non_member_cannot_add(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $setlist = SetlistFactory::new()->create();
        $song = SongFactory::new()->create(['bandSpace' => $setlist->bandSpace]);

        $this->client->loginUser($outsider);
        $this->bulk($setlist->bandSpace, $setlist, ['song_ids' => [(string) $song->id]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    /**
     * @return array{User, BandSpace, Setlist}
     */
    private function setlist(): array
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $user])->create();

        return [$user, $space, SetlistFactory::new()->create(['bandSpace' => $space, 'name' => 'Tour 2026'])];
    }

    /** @param array<string, mixed> $body */
    private function bulk(BandSpace $space, Setlist $setlist, array $body): void
    {
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/setlists/' . $setlist->id . '/items/bulk', $body, [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    /** @return list<SetlistItem> */
    private function items(Setlist $setlist): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SetlistItemRepository::class)->findBy(['setlist' => $setlist->id], ['position' => 'ASC']);
    }

    private function assertNotInRepertoire(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un des titres n\'est pas dans le répertoire de ce Band Space',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Un des titres n\'est pas dans le répertoire de ce Band Space',
        ]);
    }

    /**
     * @param array<string, mixed> $song
     *
     * @return array<string, mixed>
     */
    private function songItem(BandSpace $space, Setlist $setlist, SetlistItem $item, array $song, int $position): array
    {
        return [
            '@id' => '/api/band_spaces/' . $space->id . '/setlists/' . $setlist->id . '/items/' . $item->id,
            '@type' => 'SetlistItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $space->id,
            'setlist_id' => (string) $setlist->id,
            'type' => 'song',
            'song' => [...$song, 'has_lyrics' => false, 'archive_datetime' => null, '@type' => 'SetlistItemSongInfo'],
            'label' => null,
            'duration_override' => null,
            'note' => null,
            'transition' => null,
            'position' => $position,
        ];
    }
}

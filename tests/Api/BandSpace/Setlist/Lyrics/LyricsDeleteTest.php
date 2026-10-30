<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\Lyrics;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\LyricsRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\LyricsFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class LyricsDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /** To the Corbeille and off the song, which is then free to get new lyrics. */
    public function test_archiving_linked_lyrics_unlinks_them(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $song, 'title' => 'Ratées', 'content' => 'La']);

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame(
            [['lyrics_archived', ['title' => 'Ratées']]],
            array_map(
                static fn (BandSpaceActivity $activity): array => [$activity->type, $activity->payload],
                self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id),
            ),
        );

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repository = self::getContainer()->get(LyricsRepository::class);
        $stored = $repository->find((string) $lyrics->id);
        $this->assertInstanceOf(Lyrics::class, $stored);
        $this->assertNotNull($stored->archiveDatetime);
        $this->assertNull($stored->song);
        $this->assertSame([], $repository->findSongIdsWithLyrics([(string) $song->id]));
    }

    /** Read only with their archived song, but still free to go to the Corbeille. */
    public function test_lyrics_of_an_archived_song_can_be_archived(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $song]);

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id);
        $this->assertSame([true, null], [$stored?->archiveDatetime !== null, $stored?->song]);
    }

    public function test_a_non_member_cannot_archive(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $lyrics = LyricsFactory::createOne();

        $this->client->loginUser($stranger);
        $this->client->request('DELETE', '/api/band_spaces/' . $lyrics->bandSpace->id . '/lyrics/' . $lyrics->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu n\'es pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Tu n\'es pas membre de ce Band Space',
        ]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id)?->archiveDatetime);
    }

    public function test_archiving_twice_changes_nothing(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $archived = new DateTimeImmutable('2026-10-20 18:30:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'archiveDatetime' => $archived]);

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([], self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertEquals($archived, self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id)?->archiveDatetime);
    }

    public function test_a_space_pending_deletion_is_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);
    }
}

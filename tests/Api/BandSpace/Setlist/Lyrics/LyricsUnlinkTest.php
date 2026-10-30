<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\BandSpaceActivity;
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
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class LyricsUnlinkTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /** An archived song keeps its lyrics read only, and unlinking is how a text gets free of it. */
    public function test_lyrics_come_off_an_archived_song(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Plus jouée', 'archiveDatetime' => new DateTimeImmutable()]);
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $song, 'title' => 'Beau texte', 'content' => 'La', 'version' => 2, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/unlink', [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Beau texte',
            'content' => 'La',
            'version' => 2,
            'has_content' => true,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id)?->song);
        $this->assertSame(
            [['lyrics_unlinked', ['title' => 'Beau texte', 'song_title' => 'Plus jouée']]],
            array_map(
                static fn (BandSpaceActivity $activity): array => [$activity->type, $activity->payload],
                self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id),
            ),
        );
    }

    public function test_unlinking_lyrics_that_have_no_song_changes_nothing(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Seules', 'content' => null, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/unlink', [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Seules',
            'content' => null,
            'version' => 1,
            'has_content' => false,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
        $this->assertSame([], self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id));
    }

    public function test_a_non_member_cannot_unlink(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $song = SongFactory::new()->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $song->bandSpace, 'song' => $song]);

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $song->bandSpace->id . '/lyrics/' . $lyrics->id . '/unlink', [], self::HEADERS);

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
        $this->assertSame((string) $song->id, self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id)?->song?->id);
    }
}

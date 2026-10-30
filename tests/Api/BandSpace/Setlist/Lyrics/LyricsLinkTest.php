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
class LyricsLinkTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /** Written before the song existed, then put on it: the song's own lyrics endpoint now reads them. */
    public function test_lyrics_written_first_are_linked_to_the_song_later(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Enfin', 'tonality' => 'A']);
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Idée', 'content' => 'La', 'version' => 6, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $song->id], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Idée',
            'content' => 'La',
            'version' => 6,
            'has_content' => true,
            'song' => ['@type' => 'LyricsSongInfo', 'id' => $song->id, 'title' => 'Enfin', 'tonality' => 'A', 'archive_datetime' => null],
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame((string) $lyrics->id, self::getContainer()->get(LyricsRepository::class)->findOneBy(['song' => (string) $song->id])?->id);
        $this->assertSame(
            [['lyrics_linked', ['title' => 'Idée', 'song_title' => 'Enfin']]],
            array_map(
                static fn (BandSpaceActivity $activity): array => [$activity->type, $activity->payload],
                self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id),
            ),
        );
    }

    /** Linking never overwrites: the old lyrics have to be unlinked first. */
    public function test_a_song_that_already_has_lyrics_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->withLyrics('Les anciennes')->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'Les nouvelles']);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $song->id], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette chanson a déjà des paroles, délie-les d\'abord',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cette chanson a déjà des paroles, délie-les d\'abord',
        ]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame('Les anciennes', self::getContainer()->get(LyricsRepository::class)->findOneBy(['song' => (string) $song->id])?->content);
    }

    public function test_lyrics_already_on_another_song_are_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $first = SongFactory::new()->create(['bandSpace' => $space]);
        $second = SongFactory::new()->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $first, 'content' => 'La']);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $second->id], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ces paroles sont déjà liées à une autre chanson, délie-les d\'abord',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ces paroles sont déjà liées à une autre chanson, délie-les d\'abord',
        ]);
    }

    public function test_a_song_of_another_space_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $elsewhere = SongFactory::new()->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $elsewhere->id], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Chanson introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Chanson introuvable',
        ]);
    }

    public function test_an_archived_song_cannot_be_linked(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $song->id], self::HEADERS);

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
    }

    public function test_lyrics_in_the_corbeille_cannot_be_linked(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $song->id], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ces paroles sont dans la corbeille, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ces paroles sont dans la corbeille, les modifications sont désactivées',
        ]);
    }

    /** Read only with their archived song: they have to be unlinked from it before going anywhere. */
    public function test_lyrics_on_an_archived_song_cannot_be_moved(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $retired = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);
        $target = SongFactory::new()->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $retired]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $target->id], self::HEADERS);

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
    }

    public function test_a_non_member_cannot_link(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $song = SongFactory::new()->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $song->bandSpace]);

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $song->bandSpace->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $song->id], self::HEADERS);

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
    }

    public function test_a_space_pending_deletion_is_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', ['song_id' => $song->id], self::HEADERS);

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

    public function test_a_song_is_required(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/link', [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'song_id',
                    'message' => 'Choisis la chanson à lier',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'song_id: Choisis la chanson à lier',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'song_id: Choisis la chanson à lier',
        ]);
    }
}

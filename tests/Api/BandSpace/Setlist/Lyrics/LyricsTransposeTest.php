<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SongRepository;
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
class LyricsTransposeTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_linked_lyrics_move_with_their_song_key(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Montée', 'tonality' => 'C']);
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $song, 'title' => 'Montée', 'content' => '[C]La [G]la', 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => 2, 'expected_version' => 1], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $stored = $this->stored($lyrics);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Montée',
            'content' => '[D]La [A]la',
            'version' => 2,
            'has_content' => true,
            'song' => ['@type' => 'LyricsSongInfo', 'id' => $song->id, 'title' => 'Montée', 'tonality' => 'D', 'archive_datetime' => null],
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => $stored->updateDatetime?->format(DateTimeInterface::ATOM),
        ]);
        $storedSong = self::getContainer()->get(SongRepository::class)->find((string) $song->id);
        $this->assertInstanceOf(Song::class, $storedSong);
        $this->assertSame('D', $storedSong->tonality);
    }

    public function test_unlinked_lyrics_move_only_their_chords(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Esquisse', 'content' => '[Am]La [E]la', 'version' => 3, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => -2, 'expected_version' => 3], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $stored = $this->stored($lyrics);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Esquisse',
            'content' => '[Gm]La [D]la',
            'version' => 4,
            'has_content' => true,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => $stored->updateDatetime?->format(DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_transposition_from_a_stale_copy_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => '[C]La', 'version' => 2]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => 1, 'expected_version' => 1], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ces paroles ont été modifiées par un autre membre depuis que tu les as ouvertes. Tes modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ces paroles ont été modifiées par un autre membre depuis que tu les as ouvertes. Tes modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.',
        ]);
        $this->assertSame('[C]La', $this->stored($lyrics)->content);
    }

    public function test_a_transposition_without_a_version_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => '[C]La']);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => 1], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_PRECONDITION_REQUIRED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/428',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Indique la version des paroles sur laquelle tu as travaillé pour les enregistrer.',
            'status' => 428,
            'type' => '/errors/428',
            'description' => 'Indique la version des paroles sur laquelle tu as travaillé pour les enregistrer.',
        ]);
        $this->assertSame('[C]La', $this->stored($lyrics)->content);
    }

    public function test_lyrics_in_the_corbeille_cannot_be_transposed(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => '[C]La', 'archiveDatetime' => new DateTimeImmutable()]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => 1, 'expected_version' => 1], self::HEADERS);

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
        $this->assertSame('[C]La', $this->stored($lyrics)->content);
    }

    /** Nothing to move: no chords and no song key, so the revision stays where it was. */
    public function test_empty_unlinked_lyrics_are_left_as_they_are(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Vide', 'content' => null, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => 3, 'expected_version' => 1], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Vide',
            'content' => null,
            'version' => 1,
            'has_content' => false,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_a_non_member_cannot_transpose(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $lyrics = LyricsFactory::createOne(['content' => '[C]La']);

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $lyrics->bandSpace->id . '/lyrics/' . $lyrics->id . '/transpose', ['semitones' => 1, 'expected_version' => 1], self::HEADERS);

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
        $this->assertSame('[C]La', $this->stored($lyrics)->content);
    }

    private function stored(Lyrics $lyrics): Lyrics
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id);
        $this->assertInstanceOf(Lyrics::class, $stored);

        return $stored;
    }
}

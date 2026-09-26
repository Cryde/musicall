<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Song;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SongRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A song's lyrics, chords and singers (#1055).
 */
#[ResetDatabase]
class SongLyricsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $song = SongFactory::new()->create();

        $this->client->jsonRequest('GET', $this->url($song->bandSpace, $song), [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_member_reads_the_lyrics_with_their_singers_resolved(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $singer = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $former = UserFactory::new()->asBaseUser()->create(['username' => 'ancien', 'email' => 'ancien@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $singer])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $former, 'status' => MembershipStatus::Left])->create();
        $lyrics = "{soc: Refrain}\n<span singer=\"@[{$former->id}] @[{$singer->id}]\">[Am]Hello</span> <span singer=\"all\">[G]world</span>\n{eoc}";
        $song = SongFactory::new()->create(['bandSpace' => $space, 'tonality' => 'Am', 'lyrics' => $lyrics]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('GET', $this->url($space, $song), [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongLyrics',
            '@id' => $this->url($space, $song),
            '@type' => 'SongLyrics',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'lyrics' => $lyrics,
            'lyrics_version' => 1,
            'tonality' => 'Am',
            'singers' => [
                ['id' => $former->id, 'name' => 'ancien', 'is_former_member' => true],
                ['id' => $singer->id, 'name' => 'chanteuse', 'is_former_member' => false],
            ],
        ]);
    }

    public function test_a_non_member_cannot_read_the_lyrics(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $song = SongFactory::new()->create(['lyrics' => 'Secret']);

        $this->client->loginUser($outsider);
        $this->client->jsonRequest('GET', $this->url($song->bandSpace, $song), [], ['HTTP_ACCEPT' => 'application/ld+json']);

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

    public function test_a_song_of_another_space_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $elsewhere = SongFactory::new()->create(['lyrics' => 'Secret']);

        $this->client->loginUser($member);
        $this->client->jsonRequest('GET', $this->url($space, $elsewhere), [], ['HTTP_ACCEPT' => 'application/ld+json']);

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

    public function test_a_member_saves_the_lyrics(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Au clair de la lune', 'tonality' => 'C']);
        $lyrics = "[C]Au clair de la [G]lune, <span singer=\"@[{$member->id}]\">mon ami</span>";

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => $lyrics, 'expected_lyrics_version' => 1]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongLyrics',
            '@id' => $this->url($space, $song),
            '@type' => 'SongLyrics',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'lyrics' => $lyrics,
            'lyrics_version' => 2,
            'tonality' => 'C',
            'singers' => [['id' => $member->id, 'name' => 'batteur', 'is_former_member' => false]],
        ]);

        $refreshed = $this->refresh($song);
        $this->assertSame($lyrics, $refreshed->lyrics);
        $this->assertNotNull($refreshed->updateDatetime);
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $song->id);
        $this->assertCount(1, $activities);
        $this->assertSame('song_updated', $activities[0]->type);
    }

    /** Blank lyrics clear the song rather than store whitespace, so « has lyrics » stays honest. */
    public function test_blank_lyrics_clear_the_song(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'lyrics' => 'La la', 'lyricsVersion' => 4]);

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => "  \n ", 'expected_lyrics_version' => 4]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongLyrics',
            '@id' => $this->url($space, $song),
            '@type' => 'SongLyrics',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'lyrics' => null,
            'lyrics_version' => 5,
            'tonality' => null,
            'singers' => [],
        ]);
        $this->assertNull($this->refresh($song)->lyrics);
    }

    public function test_saving_the_same_lyrics_keeps_the_revision(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'lyrics' => 'La la', 'lyricsVersion' => 3]);

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => 'La la', 'expected_lyrics_version' => 3]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongLyrics',
            '@id' => $this->url($space, $song),
            '@type' => 'SongLyrics',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'lyrics' => 'La la',
            'lyrics_version' => 3,
            'tonality' => null,
            'singers' => [],
        ]);
        $this->assertCount(0, self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $song->id));
    }

    public function test_a_save_from_a_stale_copy_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'lyrics' => 'Version de la chanteuse', 'lyricsVersion' => 2]);

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => 'Ma version', 'expected_lyrics_version' => 1]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $detail = 'Ces paroles ont été modifiées par un autre membre depuis que vous les avez ouvertes. Vos modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 409,
            'type' => '/errors/409',
            'description' => $detail,
        ]);
        $this->assertSame('Version de la chanteuse', $this->refresh($song)->lyrics);
    }

    public function test_a_save_without_a_revision_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => 'La la']);

        $this->assertResponseStatusCodeSame(Response::HTTP_PRECONDITION_REQUIRED);
        $detail = 'Indiquez la version des paroles sur laquelle vous avez travaillé pour les enregistrer.';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/428',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 428,
            'type' => '/errors/428',
            'description' => $detail,
        ]);
    }

    public function test_a_singer_who_never_was_a_member_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->patch($space, $song, [
            'lyrics' => "<span singer=\"@[{$member->id}] @[{$stranger->id}]\">La</span> <span singer=\"@[pas-un-uuid]\">la</span>",
            'expected_lyrics_version' => 1,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'lyrics',
                    'message' => 'Un chanteur attribué n\'est pas membre de ce Band Space',
                    'code' => 'music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
                ],
            ],
            'detail' => 'lyrics: Un chanteur attribué n\'est pas membre de ce Band Space',
            'type' => '/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            'title' => 'An error occurred',
            'description' => 'lyrics: Un chanteur attribué n\'est pas membre de ce Band Space',
        ]);
    }

    public function test_lyrics_over_the_limit_are_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => str_repeat('a', 20001), 'expected_lyrics_version' => 1]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'lyrics',
                    'message' => 'Les paroles ne peuvent pas dépasser 20000 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'lyrics: Les paroles ne peuvent pas dépasser 20000 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'lyrics: Les paroles ne peuvent pas dépasser 20000 caractères',
        ]);
    }

    public function test_the_lyrics_of_an_archived_song_cannot_change(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new \DateTimeImmutable('2026-09-01')]);

        $this->client->loginUser($member);
        $this->patch($space, $song, ['lyrics' => 'La la', 'expected_lyrics_version' => 1]);

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

    public function test_transposing_moves_the_chords_and_the_key_together(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create([
            'bandSpace' => $space,
            'tonality' => 'C',
            'lyrics' => "{soc: Refrain}\n[C]Au clair <span singer=\"all\">[G/B]de la</span> [Am]lune",
            'lyricsVersion' => 2,
        ]);

        $this->client->loginUser($member);
        $this->transpose($space, $song, ['semitones' => 5, 'expected_lyrics_version' => 2]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongLyrics',
            '@id' => $this->url($space, $song),
            '@type' => 'SongLyrics',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'lyrics' => "{soc: Refrain}\n[F]Au clair <span singer=\"all\">[C/E]de la</span> [Dm]lune",
            'lyrics_version' => 3,
            'tonality' => 'F',
            'singers' => [],
        ]);
        $this->assertSame('F', $this->refresh($song)->tonality);
    }

    /** A key written as free text is kept rather than guessed at; the chords still move. */
    public function test_transposing_keeps_a_key_it_cannot_read(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'tonality' => 'Variable', 'lyrics' => '[A]Hey']);

        $this->client->loginUser($member);
        $this->transpose($space, $song, ['semitones' => -2, 'expected_lyrics_version' => 1]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongLyrics',
            '@id' => $this->url($space, $song),
            '@type' => 'SongLyrics',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'lyrics' => '[G]Hey',
            'lyrics_version' => 2,
            'tonality' => 'Variable',
            'singers' => [],
        ]);
    }

    public function test_transposing_a_stale_copy_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'tonality' => 'C', 'lyrics' => '[C]La', 'lyricsVersion' => 3]);

        $this->client->loginUser($member);
        $this->transpose($space, $song, ['semitones' => 2, 'expected_lyrics_version' => 2]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $detail = 'Ces paroles ont été modifiées par un autre membre depuis que vous les avez ouvertes. Vos modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 409,
            'type' => '/errors/409',
            'description' => $detail,
        ]);
        $this->assertSame('[C]La', $this->refresh($song)->lyrics);
    }

    public function test_a_non_member_cannot_save_lyrics(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $song = SongFactory::new()->create(['lyrics' => 'Secret']);

        $this->client->loginUser($outsider);
        $this->patch($song->bandSpace, $song, ['lyrics' => 'Piraté', 'expected_lyrics_version' => 1]);

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
        $this->assertSame('Secret', $this->refresh($song)->lyrics);
    }

    public function test_a_non_member_cannot_transpose(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $song = SongFactory::new()->create(['tonality' => 'C', 'lyrics' => '[C]La']);

        $this->client->loginUser($outsider);
        $this->transpose($song->bandSpace, $song, ['semitones' => 2, 'expected_lyrics_version' => 1]);

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
        $this->assertSame('[C]La', $this->refresh($song)->lyrics);
    }

    /** Same answer as PATCH lyrics without a revision: a precondition, not a validation error. */
    public function test_transposing_without_a_revision_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'tonality' => 'C', 'lyrics' => '[C]La']);

        $this->client->loginUser($member);
        $this->transpose($space, $song, ['semitones' => 2]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PRECONDITION_REQUIRED);
        $detail = 'Indiquez la version des paroles sur laquelle vous avez travaillé pour les enregistrer.';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/428',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 428,
            'type' => '/errors/428',
            'description' => $detail,
        ]);
    }

    public function test_a_transposition_out_of_range_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'lyrics' => '[C]La']);

        $this->client->loginUser($member);
        $this->transpose($space, $song, ['semitones' => 12, 'expected_lyrics_version' => 1]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'semitones',
                    'message' => 'La transposition doit être entre -11 et 11 demi-tons',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'semitones: La transposition doit être entre -11 et 11 demi-tons',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
            'description' => 'semitones: La transposition doit être entre -11 et 11 demi-tons',
        ]);
    }

    public function test_the_song_says_whether_it_has_lyrics(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'lyrics' => '[C]La']);

        $this->client->loginUser($member);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $space->id . '/songs/' . $song->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $space->id . '/songs/' . $song->id,
            '@type' => 'Song',
            'id' => (string) $song->id,
            'band_space_id' => (string) $space->id,
            'title' => $song->title,
            'tempo' => $song->tempo,
            'tonality' => $song->tonality,
            'reference_duration' => $song->referenceDuration,
            'notes' => $song->notes,
            'has_lyrics' => true,
            'setlists' => [],
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    private function url(BandSpace $space, Song $song): string
    {
        return '/api/band_spaces/' . $space->id . '/songs/' . $song->id . '/lyrics';
    }

    /** @param array<string, mixed> $body */
    private function patch(BandSpace $space, Song $song, array $body): void
    {
        $this->client->jsonRequest('PATCH', $this->url($space, $song), $body, [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    /** @param array<string, mixed> $body */
    private function transpose(BandSpace $space, Song $song, array $body): void
    {
        $this->client->jsonRequest('POST', $this->url($space, $song) . '/transpose', $body, [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    private function refresh(Song $song): Song
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SongRepository::class)->find((string) $song->id);
    }
}

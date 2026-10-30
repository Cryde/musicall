<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\Lyrics;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\LyricsRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class LyricsCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /** Before the song exists: the use case the whole epic is for (#1264). */
    public function test_a_member_writes_lyrics_with_no_song(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $content = "[Am]Un jour <span singer=\"@[{$member->id}]\">peut-être</span>";

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', ['title' => 'Idée du mardi', 'content' => $content], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $stored = $this->storedLyrics($space);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $stored->id,
            '@type' => 'Lyrics',
            'id' => $stored->id,
            'band_space_id' => $space->id,
            'title' => 'Idée du mardi',
            'content' => $content,
            'version' => 1,
            'has_content' => true,
            'song' => null,
            'singers' => [['id' => $member->id, 'name' => 'chanteuse', 'is_former_member' => false]],
            'archive_datetime' => null,
            'creation_datetime' => $stored->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $this->assertSame([['lyrics_added', ['title' => 'Idée du mardi', 'song_title' => null]]], $this->activities($space, (string) $stored->id));
    }

    public function test_lyrics_can_be_written_straight_onto_a_song(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Le tube', 'tonality' => 'G']);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', [
            'title' => 'Le tube',
            'content' => '   ',
            'song_id' => $song->id,
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $stored = $this->storedLyrics($space);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $stored->id,
            '@type' => 'Lyrics',
            'id' => $stored->id,
            'band_space_id' => $space->id,
            'title' => 'Le tube',
            'content' => null,
            'version' => 1,
            'has_content' => false,
            'song' => ['@type' => 'LyricsSongInfo', 'id' => $song->id, 'title' => 'Le tube', 'tonality' => 'G', 'archive_datetime' => null],
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $stored->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
        $this->assertSame((string) $song->id, $stored->song?->id);
        $this->assertSame([['lyrics_added', ['title' => 'Le tube', 'song_title' => 'Le tube']]], $this->activities($space, (string) $stored->id));
    }

    public function test_a_song_that_already_has_lyrics_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->withLyrics('Déjà écrites')->create(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', ['title' => 'Autres', 'song_id' => $song->id], self::HEADERS);

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
        $this->assertCount(1, self::getContainer()->get(LyricsRepository::class)->findAll());
    }

    public function test_a_song_of_another_space_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $elsewhere = SongFactory::new()->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', ['title' => 'Volées', 'song_id' => $elsewhere->id], self::HEADERS);

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

    public function test_an_archived_song_cannot_get_new_lyrics(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', ['title' => 'Trop tard', 'song_id' => $song->id], self::HEADERS);

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

    public function test_a_title_is_required(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', ['title' => '', 'song_id' => 'pas-un-uuid'], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=51120b12-a2bc-41bf-aa53-cd73daf330d0',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Indique un titre',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'song_id',
                    'message' => 'Identifiant de titre invalide',
                    'code' => '51120b12-a2bc-41bf-aa53-cd73daf330d0',
                ],
            ],
            'detail' => "title: Indique un titre\nsong_id: Identifiant de titre invalide",
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=51120b12-a2bc-41bf-aa53-cd73daf330d0',
            'title' => 'An error occurred',
            'description' => "title: Indique un titre\nsong_id: Identifiant de titre invalide",
        ]);
    }

    /** Refused before validation, so a stranger cannot learn who is in the space from the singer check. */
    public function test_a_non_member_is_refused_before_anything_is_checked(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        $insider = UserFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $insider])->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', [
            'title' => 'Sonde',
            'content' => "<span singer=\"@[{$insider->id}]\">la</span>",
        ], self::HEADERS);

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

    public function test_a_singer_outside_the_band_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', [
            'title' => 'Duo',
            'content' => "<span singer=\"@[{$stranger->id}]\">la</span>",
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Un chanteur attribué n\'est pas membre de ce Band Space',
                    'code' => 'music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
                ],
            ],
            'detail' => 'content: Un chanteur attribué n\'est pas membre de ce Band Space',
            'type' => '/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            'title' => 'An error occurred',
            'description' => 'content: Un chanteur attribué n\'est pas membre de ce Band Space',
        ]);
    }

    public function test_a_space_pending_deletion_is_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics', ['title' => 'Dernière'], self::HEADERS);

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

    private function storedLyrics(BandSpace $space): Lyrics
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $lyrics = self::getContainer()->get(LyricsRepository::class)->findOneBy(['bandSpace' => (string) $space->id]);
        $this->assertInstanceOf(Lyrics::class, $lyrics);

        return $lyrics;
    }

    /** @return list<array{string, mixed}> */
    private function activities(BandSpace $space, string $lyricsId): array
    {
        return array_map(
            static fn (BandSpaceActivity $activity): array => [$activity->type, $activity->payload],
            self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, $lyricsId),
        );
    }
}

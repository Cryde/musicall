<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\LyricsFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class LyricsGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_reads_the_lyrics_with_their_song_and_singers(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Au clair de la lune', 'tonality' => 'C']);
        $content = "[C]Au clair <span singer=\"@[{$member->id}]\">de la lune</span>";
        $created = new DateTime('2026-10-01 10:00:00');
        $updated = new DateTime('2026-10-02 11:00:00');
        $lyrics = LyricsFactory::createOne([
            'bandSpace' => $space,
            'song' => $song,
            'title' => 'Au clair de la lune',
            'content' => $content,
            'version' => 3,
            'creationDatetime' => $created,
            'updateDatetime' => $updated,
        ]);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Au clair de la lune',
            'content' => $content,
            'version' => 3,
            'has_content' => true,
            'song' => ['@type' => 'LyricsSongInfo', 'id' => $song->id, 'title' => 'Au clair de la lune', 'tonality' => 'C', 'archive_datetime' => null],
            'singers' => [['id' => $member->id, 'name' => 'batteur', 'is_former_member' => false]],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => $updated->format(DateTimeInterface::ATOM),
        ]);
    }

    public function test_lyrics_of_another_space_are_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $elsewhere = LyricsFactory::createOne(['content' => 'Secret']);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/lyrics/' . $elsewhere->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Paroles introuvables',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Paroles introuvables',
        ]);
    }

    public function test_a_non_member_cannot_read_them(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $lyrics = LyricsFactory::createOne(['content' => 'Secret']);

        $this->client->loginUser($stranger);
        $this->client->request('GET', '/api/band_spaces/' . $lyrics->bandSpace->id . '/lyrics/' . $lyrics->id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
}

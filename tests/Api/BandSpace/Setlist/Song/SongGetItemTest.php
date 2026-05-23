<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class SongGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_song_by_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Get Lucky',
            'tempo' => 116,
            'tonality' => 'Bm',
            'referenceDuration' => 369,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id,
            '@type' => 'Song',
            'id' => $song->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Get Lucky',
            'tempo' => 116,
            'tonality' => 'Bm',
            'reference_duration' => 369,
            'notes' => null,
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_get_archived_song_by_id_still_works(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $archivedAt = new \DateTimeImmutable('2026-05-10T10:00:00+00:00');
        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Archived',
            'archiveDatetime' => $archivedAt,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id,
            '@type' => 'Song',
            'id' => $song->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Archived',
            'tempo' => null,
            'tonality' => null,
            'reference_duration' => null,
            'notes' => null,
            'archive_datetime' => $archivedAt->format(\DateTimeInterface::ATOM),
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_get_song_cross_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBand, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/songs/' . $song->id);

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

    public function test_get_song_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id);

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

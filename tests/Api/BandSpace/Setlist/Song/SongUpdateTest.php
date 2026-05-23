<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Enum\BandSpace\BandSpaceModule;
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


#[ResetDatabase]
class SongUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_update_song(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Old title',
            'tempo' => 100,
        ])->create();
        $songId = (string) $song->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId,
            [
                'title' => 'New title',
                'tempo' => 140,
                'tonality' => 'D',
                'reference_duration' => 240,
                'notes' => 'updated notes',
            ],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $refreshed = self::getContainer()->get(SongRepository::class)->find($songId);
        $this->assertSame('New title', $refreshed->title);
        $this->assertSame(140, $refreshed->tempo);
        $this->assertSame('D', $refreshed->tonality);
        $this->assertSame(240, $refreshed->referenceDuration);
        $this->assertSame('updated notes', $refreshed->notes);
        $this->assertNotNull($refreshed->updateDatetime);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId,
            '@type' => 'Song',
            'id' => $songId,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'New title',
            'tempo' => 140,
            'tonality' => 'D',
            'reference_duration' => 240,
            'notes' => 'updated notes',
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        // Activity row recorded
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, $songId);
        $this->assertCount(1, $activities);
        $this->assertSame('song_updated', $activities[0]->type);
    }

    public function test_update_song_partial_patch_keeps_unset_fields(): void
    {
        // Regression guard for PATCH semantics: when the client sends only a
        // subset of fields, the others must keep their current values (via the
        // provider-then-merge flow), not get nulled out by the processor.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Original',
            'tempo' => 100,
            'tonality' => 'Am',
            'referenceDuration' => 240,
            'notes' => 'original notes',
        ])->create();
        $songId = (string) $song->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId,
            ['title' => 'Renamed only'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId,
            '@type' => 'Song',
            'id' => $songId,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'Renamed only',
            'tempo' => 100,
            'tonality' => 'Am',
            'reference_duration' => 240,
            'notes' => 'original notes',
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $this->getResponseAsArray()['update_datetime'],
        ]);
    }

    public function test_update_song_cross_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBand, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $myBand->id . '/songs/' . $song->id,
            ['title' => 'X'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

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

    public function test_update_song_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
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

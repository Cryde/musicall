<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SongRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class SongCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_song_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs',
            ['title' => 'Wonderwall'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(SongRepository::class);
        $songs = $repo->findByBandSpace($bandSpace);
        $this->assertCount(1, $songs);
        $song = $songs[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id,
            '@type' => 'Song',
            'id' => $song->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Wonderwall',
            'tempo' => null,
            'tonality' => null,
            'reference_duration' => null,
            'notes' => null,
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        // Activity row recorded
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, $song->id);
        $this->assertCount(1, $activities);
        $this->assertSame('song_added', $activities[0]->type);
        $this->assertSame(['title' => 'Wonderwall'], $activities[0]->payload);
    }

    public function test_create_song_full(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs',
            [
                'title' => 'Smells Like Teen Spirit',
                'tempo' => 117,
                'tonality' => 'Fm',
                'reference_duration' => 301,
                'notes' => 'Tuning E flat',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(SongRepository::class);
        $songs = $repo->findByBandSpace($bandSpace);
        $this->assertCount(1, $songs);
        $song = $songs[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id,
            '@type' => 'Song',
            'id' => $song->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Smells Like Teen Spirit',
            'tempo' => 117,
            'tonality' => 'Fm',
            'reference_duration' => 301,
            'notes' => 'Tuning E flat',
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_song_validation_title_required(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs',
            ['title' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Veuillez spécifier un titre',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'title: Veuillez spécifier un titre',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'title: Veuillez spécifier un titre',
        ]);
    }

    public function test_create_song_validation_tempo_out_of_range(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs',
            ['title' => 'X', 'tempo' => 999],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'tempo',
                    'message' => 'Le tempo doit être entre 1 et 400 BPM',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'tempo: Le tempo doit être entre 1 et 400 BPM',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
            'description' => 'tempo: Le tempo doit être entre 1 et 400 BPM',
        ]);
    }

    public function test_create_song_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs',
            ['title' => 'Should be rejected'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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

        // No song persisted
        $repo = self::getContainer()->get(SongRepository::class);
        $this->assertCount(0, $repo->findByBandSpace($bandSpace));
    }
}

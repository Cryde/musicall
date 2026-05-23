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
use App\Repository\BandSpace\BandSpaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class SongDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_song_soft_deletes(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Doomed'])->create();
        $songId = (string) $song->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Row still exists (soft delete). Refresh via the container to drop
        // the request-scoped EM state without orphaning the $bandSpace ref.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(SongRepository::class);
        $refreshed = $repo->find($songId);
        $this->assertNotNull($refreshed);
        $this->assertNotNull($refreshed->archiveDatetime);

        // Excluded from collection / included with includeArchived. Re-fetch
        // the band space because clear() detached the test's reference.
        $bandSpaceId = (string) $bandSpace->id;
        $reloadedBand = self::getContainer()->get(BandSpaceRepository::class)->find($bandSpaceId);
        $this->assertCount(0, $repo->findByBandSpace($reloadedBand));
        $this->assertCount(1, $repo->findByBandSpace($reloadedBand, true));

        // Activity row recorded
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, $songId);
        $this->assertCount(1, $activities);
        $this->assertSame('song_archived', $activities[0]->type);
        $this->assertSame(['title' => 'Doomed'], $activities[0]->payload);
    }

    public function test_delete_song_twice_is_idempotent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new \DateTimeImmutable('2026-05-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Only the original archive activity (none added on re-delete)
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, (string) $song->id);
        $this->assertCount(0, $activities);
    }

    public function test_delete_song_cross_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBand, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $myBand->id . '/songs/' . $song->id);

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

    public function test_delete_song_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id);

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

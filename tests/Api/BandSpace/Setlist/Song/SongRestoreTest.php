<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Song;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\SongWriteGuard;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Archiving a title used to be one way: nothing listed the archived row and nothing cleared the flag,
 * so an accidental delete took the title out of the repertoire for good, and since #824 it could not
 * even be edited back into shape.
 */
#[ResetDatabase]
class SongRestoreTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /**
     * The membership role is pinned to User rather than left to the factory default, because the rule
     * under test is that restoring is NOT admin gated: any member may archive a title, so any member
     * brings it back.
     */
    public function test_restore_puts_the_title_back_in_the_repertoire(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'role' => Role::User])->create();

        $song = $this->archivedSong($bandSpace, 'Trop tard');
        $songId = (string) $song->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Song',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId,
            '@type' => 'Song',
            'id' => $songId,
            'band_space_id' => $bandSpace->id,
            'title' => 'Trop tard',
            'tempo' => 128,
            'tonality' => 'Am',
            'reference_duration' => 210,
            'notes' => null,
            'archive_datetime' => null,
            'creation_datetime' => $song->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Setlist, $songId);
        $this->assertCount(1, $activities);
        $this->assertSame('song_unarchived', $activities[0]->type);
        $this->assertSame(['title' => 'Trop tard'], $activities[0]->payload);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SongRepository::class)->find($songId);
        $this->assertInstanceOf(Song::class, $stored);
        $this->assertNull($stored->archiveDatetime);

        // The other half of #824: the guard that froze this row has to let go once it is restored,
        // otherwise the title comes back visible but still unwritable. Instantiated rather than
        // pulled from the container because it has no dependencies and this is the real class.
        (new SongWriteGuard())->assertWritable($stored);
        $this->addToAssertionCount(1);
    }

    /**
     * The restored title is back in the live repertoire, which is the list the Répertoire table renders
     * and the one an archived row was missing from.
     */
    public function test_a_restored_title_is_listed_again(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = $this->archivedSong($bandSpace, 'Trop tard');
        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/songs/' . $song->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(BandSpaceRepository::class)->find($bandSpaceId);
        $songRepository = self::getContainer()->get(SongRepository::class);

        $live = $songRepository->findByBandSpace($reloadedBand);
        $this->assertCount(1, $live);
        $this->assertSame('Trop tard', $live[0]->title);

        $this->assertCount(0, $songRepository->findByBandSpace($reloadedBand, true), 'The trash is empty again');
    }

    public function test_restore_a_live_title_conflicts(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Still live',
            'creationDatetime' => new DateTime('2026-01-05T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Cette chanson n'est pas dans la corbeille",
            'status' => 409,
            'type' => '/errors/409',
            'description' => "Cette chanson n'est pas dans la corbeille",
        ]);
    }

    public function test_restore_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $song = $this->archivedSong($bandSpace, 'Trop tard');
        $songId = (string) $song->id;

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songId . '/restore',
            [],
            self::HEADERS,
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SongRepository::class)->find($songId);
        $this->assertNotNull($stored?->archiveDatetime);
    }

    /**
     * The band space in the path is the one the title is looked up in, so a member of A cannot reach
     * into B's trash by pointing his own space at their song id.
     */
    public function test_restore_a_title_of_another_band_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $theirSong = $this->archivedSong($otherBand, 'Their title');
        $theirSongId = (string) $theirSong->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $myBand->id . '/songs/' . $theirSongId . '/restore',
            [],
            self::HEADERS,
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(SongRepository::class)->find($theirSongId);
        $this->assertNotNull($stored?->archiveDatetime);
    }

    public function test_restore_unauthenticated(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $song = $this->archivedSong($bandSpace, 'Trop tard');

        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/restore',
            [],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    private function archivedSong(BandSpace $bandSpace, string $title): Song
    {
        return SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => $title,
            'tempo' => 128,
            'tonality' => 'Am',
            'referenceDuration' => 210,
            'creationDatetime' => new DateTime('2026-01-05T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-06-10T09:00:00+00:00'),
        ])->create();
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
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

/**
 * Several songs to the trash from the repertoire's selection (#1063).
 */
#[ResetDatabase]
class SongsArchiveTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_selected_songs_go_to_the_trash_with_one_activity_entry(): void
    {
        [$user, $space] = $this->band();
        $first = SongFactory::new()->create(['bandSpace' => $space]);
        $second = SongFactory::new()->create(['bandSpace' => $space]);
        $kept = SongFactory::new()->create(['bandSpace' => $space]);

        $this->client->loginUser($user);
        $this->archive($space, [(string) $first->id, (string) $second->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNotNull($this->song((string) $first->id)->archiveDatetime);
        $this->assertNotNull($this->song((string) $second->id)->archiveDatetime);
        $this->assertNull($this->song((string) $kept->id)->archiveDatetime);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $space->id);
        $this->assertCount(1, $activities);
        $this->assertSame('songs_archived', $activities[0]->type);
        $this->assertSame(['count' => 2], $activities[0]->payload);
    }

    public function test_a_song_already_in_the_trash_is_left_as_it_is(): void
    {
        [$user, $space] = $this->band();
        $archivedAt = new \DateTimeImmutable('2026-09-01T10:00:00+00:00');
        $already = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => $archivedAt]);

        $this->client->loginUser($user);
        $this->archive($space, [(string) $already->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertEquals($archivedAt, $this->song((string) $already->id)->archiveDatetime);
        $this->assertCount(0, self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $space->id));
    }

    public function test_a_song_of_another_band_refuses_the_whole_request(): void
    {
        [$user, $space] = $this->band();
        $mine = SongFactory::new()->create(['bandSpace' => $space]);
        $theirs = SongFactory::new()->create(['bandSpace' => BandSpaceFactory::new()->create()]);

        $this->client->loginUser($user);
        $this->archive($space, [(string) $mine->id, (string) $theirs->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un des titres n\'est pas dans le répertoire de ce Band Space',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Un des titres n\'est pas dans le répertoire de ce Band Space',
        ]);
        $this->assertNull($this->song((string) $mine->id)->archiveDatetime);
        $this->assertNull($this->song((string) $theirs->id)->archiveDatetime);
    }

    public function test_no_song_is_refused(): void
    {
        [$user, $space] = $this->band();

        $this->client->loginUser($user);
        $this->archive($space, []);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'song_ids',
                    'message' => 'Choisissez au moins un titre',
                    'code' => 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
                ],
            ],
            'detail' => 'song_ids: Choisissez au moins un titre',
            'type' => '/validation_errors/bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            'title' => 'An error occurred',
            'description' => 'song_ids: Choisissez au moins un titre',
        ]);
    }

    public function test_a_non_member_cannot_archive(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $song = SongFactory::new()->create();

        $this->client->loginUser($outsider);
        $this->archive($song->bandSpace, [(string) $song->id]);

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
        $this->assertNull($this->song((string) $song->id)->archiveDatetime);
    }

    /** @return array{User, BandSpace} */
    private function band(): array
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $user])->create();

        return [$user, $space];
    }

    /** @param list<string> $songIds */
    private function archive(BandSpace $space, array $songIds): void
    {
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/songs/archive', ['song_ids' => $songIds], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    private function song(string $id): \App\Entity\BandSpace\Song
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SongRepository::class)->find($id);
    }
}

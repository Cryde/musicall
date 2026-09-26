<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_rename_setlist(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Old name',
        ])->create();
        $setlistId = (string) $setlist->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId,
            ['name' => 'New name'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $refreshed = self::getContainer()->get(SetlistRepository::class)->find($setlistId);
        $this->assertSame('New name', $refreshed->name);
        $this->assertNotNull($refreshed->updateDatetime);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId,
            '@type' => 'Setlist',
            'id' => $setlistId,
            'band_space_id' => (string) $bandSpace->id,
            'name' => 'New name',
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'items' => [],
            'total_duration_seconds' => 0,
            'target_duration' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Setlist, $setlistId);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_renamed', $activities[0]->type);
    }

    public function test_rename_cross_band_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBand, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $myBand->id . '/setlists/' . $setlist->id,
            ['name' => 'X'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Setlist introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Setlist introuvable',
        ]);
    }

    public function test_rename_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id,
            ['name' => 'Hacked'],
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

    /** The duration target (#1061) is set through the same PATCH, and is not a rename. */
    public function test_set_the_duration_target(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Tour 2026'])->create();
        $setlistId = (string) $setlist->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId,
            ['target_duration' => 2700],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $refreshed = self::getContainer()->get(SetlistRepository::class)->find($setlistId);
        $this->assertSame(2700, $refreshed->targetDuration);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistId,
            '@type' => 'Setlist',
            'id' => $setlistId,
            'band_space_id' => (string) $bandSpace->id,
            'name' => 'Tour 2026',
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'items' => [],
            'total_duration_seconds' => 0,
            'target_duration' => 2700,
        ]);
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($bandSpace, BandSpaceModule::Setlist, $setlistId);
        $this->assertCount(0, $activities);
    }

    public function test_clear_the_duration_target(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Tour 2026', 'targetDuration' => 2700])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id,
            ['target_duration' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $refreshed = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Setlist',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id,
            '@type' => 'Setlist',
            'id' => (string) $setlist->id,
            'band_space_id' => (string) $bandSpace->id,
            'name' => 'Tour 2026',
            'archive_datetime' => null,
            'creation_datetime' => $setlist->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'items' => [],
            'total_duration_seconds' => 0,
            'target_duration' => null,
        ]);
    }

    public function test_a_duration_target_out_of_range_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id,
            ['target_duration' => 30],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'target_duration',
                    'message' => 'L\'objectif doit être entre 1 minute et 24 heures',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'target_duration: L\'objectif doit être entre 1 minute et 24 heures',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
            'description' => 'target_duration: L\'objectif doit être entre 1 minute et 24 heures',
        ]);
    }
}

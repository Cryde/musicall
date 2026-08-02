<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderUnarchiveTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_unarchive_restores_the_rider(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Technical rider 2025',
            'createdBy' => $user,
            'creationDatetime' => new DateTime('2025-01-15T10:00:00+00:00'),
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/unarchive',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            '@type' => 'TechRider',
            'id' => $rider->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Technical rider 2025',
            'created_by_username' => $user->username,
            'archive_datetime' => null,
            'creation_datetime' => $rider->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
            'sections' => [],
            'section_count' => 0,
        ]);

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Rider, $rider->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_unarchived', $activities[0]->type);
        $this->assertSame(['name' => 'Technical rider 2025'], $activities[0]->payload);
    }

    public function test_unarchive_a_live_rider_conflicts(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Still live'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/unarchive',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce tech rider n'est pas archivé",
            'status' => 409,
            'type' => '/errors/409',
            'description' => "Ce tech rider n'est pas archivé",
        ]);
    }

    public function test_unarchive_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Protected',
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/unarchive',
            [],
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
    }
}

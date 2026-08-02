<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /**
     * DELETE archives rather than removing the row: the rider must still be there
     * afterwards, findable through the archive view.
     */
    public function test_delete_archives_the_rider_and_keeps_the_row(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Technical rider 2025',
            'creationDatetime' => new DateTime('2025-01-15T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repository = self::getContainer()->get(TechRiderRepository::class);
        $this->assertCount(0, $repository->findByBandSpace($bandSpace));

        $archived = $repository->findByBandSpace($bandSpace, archivedOnly: true);
        $this->assertCount(1, $archived);
        $this->assertSame((string) $rider->id, (string) $archived[0]->id);
        $this->assertTrue($archived[0]->isArchived());

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Rider, $rider->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_archived', $activities[0]->type);
        $this->assertSame(['name' => 'Technical rider 2025'], $activities[0]->payload);
    }

    public function test_delete_an_already_archived_rider_conflicts(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Already archived',
            'archiveDatetime' => new DateTimeImmutable('2026-03-01T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce tech rider est déjà archivé',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ce tech rider est déjà archivé',
        ]);
    }

    public function test_delete_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Protected'])->create();

        $this->client->loginUser($outsider);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

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

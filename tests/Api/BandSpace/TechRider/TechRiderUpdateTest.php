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
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_rename_tech_rider(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Draft',
            'createdBy' => $user,
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            ['name' => 'Technical rider 2026'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            '@type' => 'TechRider',
            'id' => $rider->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Technical rider 2026',
            'created_by_username' => $user->username,
            'archive_datetime' => null,
            'creation_datetime' => $rider->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => $rider->updateDatetime?->format(DateTimeInterface::ATOM),
            'sections' => [],
            'section_count' => 0,
        ]);

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Rider, $rider->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_renamed', $activities[0]->type);
        $this->assertSame(['name' => 'Technical rider 2026'], $activities[0]->payload);
    }

    public function test_rename_tech_rider_name_required(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Draft'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            ['name' => ''],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'name: Veuillez spécifier un nom',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'name: Veuillez spécifier un nom',
        ]);
    }

    public function test_rename_tech_rider_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Protected'])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            ['name' => 'Hijacked'],
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

    public function test_rename_tech_rider_from_another_band_space_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();

        $theirs = TechRiderFactory::new(['bandSpace' => $otherBand, 'name' => 'Theirs'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $myBand->id . '/tech_riders/' . $theirs->id,
            ['name' => 'Hijacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tech rider introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Tech rider introuvable',
        ]);
    }
}

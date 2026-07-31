<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_admin_schedules_the_deletion(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpaceId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The space is only flagged: app:band-space:purge is what actually deletes it.
        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships($bandSpaceId);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->deletionScheduledDatetime);
        $this->assertSame(
            (new \DateTimeImmutable('+30 days'))->format('Y-m-d'),
            $reloaded->deletionScheduledDatetime->format('Y-m-d'),
        );

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Settings, $bandSpaceId);
        $this->assertCount(1, $activities);
        $this->assertSame('deletion_scheduled', $activities[0]->type);
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_scheduling_the_deletion_notifies_the_other_members(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpaceId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $notifications = $notificationRepository->findForRecipient($member, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceDeletionScheduled, $notifications[0]->type);

        $scheduledFor = self::getContainer()->get(BandSpaceRepository::class)
            ->findOneByIdWithMemberships($bandSpaceId)
            ?->deletionScheduledDatetime;
        $this->assertNotNull($scheduledFor);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'Mon groupe',
            'scheduled_for' => $scheduledFor->format(\DateTimeInterface::ATOM),
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);

        // The admin who asked for the deletion is never notified.
        $this->assertCount(0, $notificationRepository->findForRecipient($admin, 10, 0));
    }

    public function test_regular_member_cannot_schedule_the_deletion(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez être administrateur pour effectuer cette action',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous devez être administrateur pour effectuer cette action',
        ]);

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships((string) $bandSpace->id);
        $this->assertNull($reloaded?->deletionScheduledDatetime);
    }

    public function test_non_member_cannot_schedule_the_deletion(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($outsider);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id);

        // The provider runs before the processor, so a non-member is rejected by the membership check.
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You are not a member of this band space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You are not a member of this band space',
        ]);
    }

    public function test_scheduling_twice_conflicts(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+10 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'La suppression de cet espace est déjà programmée',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'La suppression de cet espace est déjà programmée',
        ]);
    }

    public function test_anonymous_cannot_schedule_the_deletion(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}

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
class BandSpaceRestoreTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_admin_cancels_a_scheduled_deletion(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'name' => 'Mon groupe',
            'deletionScheduledDatetime' => new \DateTimeImmutable('+10 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpaceId . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships($bandSpaceId);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->deletionScheduledDatetime);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Settings, $bandSpaceId);
        $this->assertCount(1, $activities);
        $this->assertSame('deletion_cancelled', $activities[0]->type);
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_cancelling_notifies_the_other_members(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create([
            'name' => 'Mon groupe',
            'deletionScheduledDatetime' => new \DateTimeImmutable('+10 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpaceId . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $notifications = $notificationRepository->findForRecipient($member, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceDeletionCancelled, $notifications[0]->type);
        // scheduled_for is null: the payload is built after the column was cleared.
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'Mon groupe',
            'scheduled_for' => null,
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);

        $this->assertCount(0, $notificationRepository->findForRecipient($admin, 10, 0));
    }

    public function test_cancelling_when_nothing_is_scheduled_conflicts(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'La suppression de cet espace n\'est pas programmée',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'La suppression de cet espace n\'est pas programmée',
        ]);
    }

    public function test_regular_member_cannot_cancel(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+10 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/restore', [], [], self::HEADERS);

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
        $this->assertNotNull($reloaded?->deletionScheduledDatetime);
    }

    public function test_non_member_cannot_cancel(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+10 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($outsider);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/restore', [], [], self::HEADERS);

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

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships((string) $bandSpace->id);
        $this->assertNotNull($reloaded?->deletionScheduledDatetime);
    }

    public function test_anonymous_cannot_cancel(): void
    {
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+10 days'),
        ]);

        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}

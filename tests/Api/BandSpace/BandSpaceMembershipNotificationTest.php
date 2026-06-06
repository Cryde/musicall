<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceMembershipNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_promoting_a_member_notifies_them(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $member,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembership->id,
            ['role' => 'admin'],
            self::PATCH_HEADERS
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembership->id,
            '@type' => 'BandSpaceMember',
            'id' => $memberMembership->id,
            'band_space_id' => $bandSpaceId,
            'user_id' => $member->id,
            'username' => 'member_user',
            'role' => 'admin',
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($member, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceRoleChanged, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'Mon groupe',
            'from' => 'user',
            'to' => 'admin',
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);

        // The acting admin is never notified.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    public function test_demoting_a_member_notifies_them(): void
    {
        $admin1 = UserFactory::new()->asBaseUser()->create();
        $admin2 = UserFactory::new()->create(['username' => 'admin2', 'email' => 'admin2@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin1, 'role' => Role::Admin])->create();
        $admin2Membership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $admin2,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $adminId = (string) $admin1->id;
        $adminUsername = $admin1->username;

        $this->client->loginUser($admin1);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/members/' . $admin2Membership->id,
            ['role' => 'user'],
            self::PATCH_HEADERS
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/members/' . $admin2Membership->id,
            '@type' => 'BandSpaceMember',
            'id' => $admin2Membership->id,
            'band_space_id' => $bandSpaceId,
            'user_id' => $admin2->id,
            'username' => 'admin2',
            'role' => 'user',
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin2, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceRoleChanged, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'Mon groupe',
            'from' => 'admin',
            'to' => 'user',
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);

        // The acting admin is never notified.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin1, 10, 0));
    }

    public function test_removing_a_member_notifies_them(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembership->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($member, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceMemberRemoved, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'Mon groupe',
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);

        // The acting admin is never notified.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    public function test_self_demote_does_not_notify(): void
    {
        $admin1 = UserFactory::new()->asBaseUser()->create();
        $admin2 = UserFactory::new()->create(['username' => 'admin2', 'email' => 'admin2@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        $admin1Membership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $admin1,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();
        // A second admin so the self-demotion is allowed (the sole-admin guard does not trigger).
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin2, 'role' => Role::Admin])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($admin1);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/members/' . $admin1Membership->id,
            ['role' => 'user'],
            self::PATCH_HEADERS
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/members/' . $admin1Membership->id,
            '@type' => 'BandSpaceMember',
            'id' => $admin1Membership->id,
            'band_space_id' => $bandSpaceId,
            'user_id' => $admin1->id,
            'username' => 'base_admin',
            'role' => 'user',
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);

        // Actor == target: no self-notification.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin1, 10, 0));
    }

    public function test_no_op_role_write_does_not_notify(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $member,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembership->id,
            ['role' => 'user'], // already a user - role does not change
            self::PATCH_HEADERS
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembership->id,
            '@type' => 'BandSpaceMember',
            'id' => $memberMembership->id,
            'band_space_id' => $bandSpaceId,
            'user_id' => $member->id,
            'username' => 'member_user',
            'role' => 'user',
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($member, 10, 0));
    }

    public function test_notification_failure_does_not_break_the_role_update(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $member,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();
        $memberMembershipId = $memberMembership->id;
        $bandSpaceId = (string) $bandSpace->id;

        // A notification failure must never roll back or 500 the role update (epic #689 contract item 1).
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembershipId,
            ['role' => 'admin'],
            self::PATCH_HEADERS
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/members/' . $memberMembershipId,
            '@type' => 'BandSpaceMember',
            'id' => $memberMembershipId,
            'band_space_id' => $bandSpaceId,
            'user_id' => $member->id,
            'username' => 'member_user',
            'role' => 'admin',
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);

        $updated = self::getContainer()->get(BandSpaceMembershipRepository::class)->find($memberMembershipId);
        $this->assertSame(Role::Admin, $updated->role);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($member, 10, 0));
    }

    public function test_notification_failure_does_not_break_the_removal(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();
        $memberMembershipId = $memberMembership->id;

        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembershipId);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $kicked = self::getContainer()->get(BandSpaceMembershipRepository::class)->find($memberMembershipId);
        $this->assertSame(MembershipStatus::Kicked, $kicked->status);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($member, 10, 0));
    }

    private function throwingNotificationCreator(): NotificationCreator
    {
        return new readonly class extends NotificationCreator {
            public function __construct()
            {
            }

            public function create(User $recipient, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }

            public function createForRecipients(iterable $recipients, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }
        };
    }
}

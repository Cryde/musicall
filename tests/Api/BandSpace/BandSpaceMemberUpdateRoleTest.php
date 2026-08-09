<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceMemberUpdateRoleTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_promote_member_to_admin(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin, 'creationDatetime' => new \DateTime('2024-01-01 10:00:00')])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User, 'creationDatetime' => new \DateTime('2024-01-02 10:00:00')])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id,
            ['role' => 'admin'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id,
            '@type' => 'BandSpaceMember',
            'id' => $memberMembership->id,
            'band_space_id' => $bandSpace->id,
            'user_id' => $member->id,
            'username' => 'member_user',
            'role' => 'admin',
            'stage_name' => null,
            'display_name' => 'member_user',
            'instruments' => [],
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $member->id);
        $this->assertCount(1, $activities);
        $this->assertSame('member_role_changed', $activities[0]->type);
        $this->assertSame(
            [
                'from' => 'user',
                'to' => 'admin',
                'target_user_id' => $member->id,
                'target_username' => 'member_user',
            ],
            $activities[0]->payload,
        );
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_changing_the_role_of_a_removed_member_notifies_nobody(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $removed = UserFactory::new()->create(['username' => 'removed_user', 'email' => 'removed@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin, 'creationDatetime' => new \DateTime('2024-01-01 10:00:00')])->create();
        // A closed membership can still be patched: the update processor resolves the member by id,
        // not by status.
        $removedMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $removed,
            'role' => Role::User,
            'status' => MembershipStatus::Kicked,
            'leftDatetime' => new \DateTime('2024-06-01 10:00:00'),
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $removedMembership->id,
            ['role' => 'admin'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $removedMembership->id,
            '@type' => 'BandSpaceMember',
            'id' => $removedMembership->id,
            'band_space_id' => $bandSpace->id,
            'user_id' => $removed->id,
            'username' => 'removed_user',
            'role' => 'admin',
            'stage_name' => null,
            'display_name' => 'removed_user',
            'instruments' => [],
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'kicked',
            'left_datetime' => '2024-06-01T10:00:00+00:00',
        ]);

        // A role nobody can exercise is not news worth a bell.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_demote_admin_to_user(): void
    {
        $admin1 = UserFactory::new()->asBaseUser()->create();
        $admin2 = UserFactory::new()->create(['username' => 'admin2', 'email' => 'admin2@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin1, 'role' => Role::Admin, 'creationDatetime' => new \DateTime('2024-01-01 10:00:00')])->create();
        $admin2Membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin2, 'role' => Role::Admin, 'creationDatetime' => new \DateTime('2024-01-02 10:00:00')])->create();

        $this->client->loginUser($admin1);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $admin2Membership->id,
            ['role' => 'user'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $admin2Membership->id,
            '@type' => 'BandSpaceMember',
            'id' => $admin2Membership->id,
            'band_space_id' => $bandSpace->id,
            'user_id' => $admin2->id,
            'username' => 'admin2',
            'role' => 'user',
            'stage_name' => null,
            'display_name' => 'admin2',
            'instruments' => [],
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);
    }

    public function test_cannot_demote_self_when_only_admin(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $adminMembership->id,
            ['role' => 'user'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez pas vous rétrograder car vous êtes le seul administrateur',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Vous ne pouvez pas vous rétrograder car vous êtes le seul administrateur',
        ]);
    }

    /**
     * Reactivation used to be a second, silent door into the space: no consent from the person, no
     * refusal on a space pending deletion, and an excluded admin walked back in with their powers.
     * Joining is the invitation flow's job, and that flow is where those rules live.
     */
    public function test_a_kicked_membership_cannot_be_reactivated_through_the_status(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $kicked = UserFactory::new()->create(['username' => 'kicked_user', 'email' => 'kicked@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $kickedMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $kicked,
            'role' => Role::Admin,
            'status' => MembershipStatus::Kicked,
            'leftDatetime' => new \DateTime('2024-06-01 10:00:00'),
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $kickedMembership->id,
            ['status' => 'active'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le statut d'un membre ne se modifie pas ici. Un membre exclu ou parti revient en acceptant une nouvelle invitation.",
            'status' => 409,
            'type' => '/errors/409',
            'description' => "Le statut d'un membre ne se modifie pas ici. Un membre exclu ou parti revient en acceptant une nouvelle invitation.",
        ]);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertSame(MembershipStatus::Kicked, $membershipRepository->find($kickedMembership->id)->status);
    }

    public function test_a_member_who_left_cannot_be_dragged_back_in_through_the_status(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $gone = UserFactory::new()->create(['username' => 'gone_user', 'email' => 'gone@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $goneMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $gone,
            'role' => Role::User,
            'status' => MembershipStatus::Left,
            'leftDatetime' => new \DateTime('2024-06-01 10:00:00'),
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $goneMembership->id,
            ['status' => 'active'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le statut d'un membre ne se modifie pas ici. Un membre exclu ou parti revient en acceptant une nouvelle invitation.",
            'status' => 409,
            'type' => '/errors/409',
            'description' => "Le statut d'un membre ne se modifie pas ici. Un membre exclu ou parti revient en acceptant une nouvelle invitation.",
        ]);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertSame(MembershipStatus::Left, $membershipRepository->find($goneMembership->id)->status);
    }

    /**
     * Sending back the status the member already has is not an attempt to change anything, so a client
     * that PATCHes the whole object it just read still gets its role change through.
     */
    public function test_resending_the_current_status_alongside_a_role_is_not_refused(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin, 'creationDatetime' => new \DateTime('2024-01-01 10:00:00')])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User, 'creationDatetime' => new \DateTime('2024-01-02 10:00:00')])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id,
            ['role' => 'admin', 'status' => 'active'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id,
            '@type' => 'BandSpaceMember',
            'id' => $memberMembership->id,
            'band_space_id' => $bandSpace->id,
            'user_id' => $member->id,
            'username' => 'member_user',
            'role' => 'admin',
            'stage_name' => null,
            'display_name' => 'member_user',
            'instruments' => [],
            'profile_picture_url' => null,
            'creation_datetime' => '2024-01-02T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);
    }

    public function test_non_admin_cannot_update_role(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $adminMembership->id,
            ['role' => 'user'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

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
    }
}

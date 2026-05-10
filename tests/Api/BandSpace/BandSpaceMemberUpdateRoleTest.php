<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
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

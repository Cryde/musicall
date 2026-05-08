<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceLeaveTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_member_can_leave(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/leave',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertFalse($membershipRepository->isMember($bandSpace->_real(), $member->_real()));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Settings, $member->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('member_left', $activities[0]->type);
        $this->assertSame(
            ['target_user_id' => $member->_real()->id, 'target_username' => 'member_user'],
            $activities[0]->payload,
        );
        $this->assertSame($member->_real()->id, $activities[0]->actor?->id);
    }

    public function test_admin_can_leave_when_other_admins_exist(): void
    {
        $admin1 = UserFactory::new()->asBaseUser()->create();
        $admin2 = UserFactory::new()->create(['username' => 'admin2', 'email' => 'admin2@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin1, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin2, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin1->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/leave',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_only_admin_cannot_leave(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/leave',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez promouvoir un autre membre administrateur avant de quitter',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Vous devez promouvoir un autre membre administrateur avant de quitter',
        ]);
    }

    public function test_non_member_cannot_leave(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($outsider->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/leave',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

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
    }
}

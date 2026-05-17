<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceActivityCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_admin_lists_all_activities(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Task,
            'type' => 'status_changed',
            'actor' => $admin,
            'payload' => ['from' => 'todo', 'to' => 'done'],
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();

        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Finance,
            'type' => 'entry_created',
            'actor' => $admin,
            'payload' => ['label' => 'Studio'],
            'creationDatetime' => new \DateTime('2026-04-02 10:00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(2, $data['totalItems']);
        $this->assertCount(2, $data['member']);
        // ordered DESC by creation_datetime
        $this->assertSame('entry_created', $data['member'][0]['type']);
        $this->assertSame('finance', $data['member'][0]['module']);
        $this->assertSame('status_changed', $data['member'][1]['type']);
        $this->assertSame('task', $data['member'][1]['module']);
        $this->assertSame($admin->id, $data['member'][0]['actor']['id']);
        $this->assertSame($admin->username, $data['member'][0]['actor']['username']);
    }

    public function test_filter_by_module(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'status_changed', 'actor' => $admin])->create();
        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Finance, 'type' => 'entry_created', 'actor' => $admin])->create();
        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Agenda, 'type' => 'entry_created', 'actor' => $admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?module[]=task&module[]=agenda',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(2, $data['totalItems']);
        $modules = array_map(fn(array $a) => $a['module'], $data['member']);
        sort($modules);
        $this->assertSame(['agenda', 'task'], $modules);
    }

    public function test_filter_by_actor(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'status_changed', 'actor' => $admin])->create();
        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'comment_added', 'actor' => $member])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?actor_id=' . $member->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertSame('comment_added', $data['member'][0]['type']);
        $this->assertSame($member->id, $data['member'][0]['actor']['id']);
    }

    public function test_filter_by_type(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'status_changed', 'actor' => $admin])->create();
        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'comment_added', 'actor' => $admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?type=status_changed',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertSame('status_changed', $data['member'][0]['type']);
    }

    public function test_filter_by_date_range(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'status_changed', 'actor' => $admin, 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'status_changed', 'actor' => $admin, 'creationDatetime' => new \DateTime('2026-03-15 10:00:00')])->create();
        BandSpaceActivityFactory::new(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Task, 'type' => 'status_changed', 'actor' => $admin, 'creationDatetime' => new \DateTime('2026-06-01 10:00:00')])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?from=2026-02-01T00:00:00%2B00:00&to=2026-04-01T00:00:00%2B00:00',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertStringStartsWith('2026-03-15', $data['member'][0]['creation_datetime']);
    }

    public function test_anonymous_actor_renders_as_null(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Settings,
            'type' => 'invitation_expired',
            'actor' => null,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertNull($data['member'][0]['actor']);
    }

    public function test_non_admin_member_forbidden(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_non_member_forbidden(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => UserFactory::new()->create(['username' => 'admin', 'email' => 'a@a.com']), 'role' => Role::Admin])->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_pagination(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        for ($i = 0; $i < 60; $i++) {
            BandSpaceActivityFactory::new([
                'bandSpace' => $bandSpace,
                'module' => BandSpaceModule::Task,
                'type' => 'status_changed',
                'actor' => $admin,
                'creationDatetime' => new \DateTime(sprintf('2026-01-%02d 10:00:00', ($i % 28) + 1)),
            ])->create();
        }

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?page=1',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(60, $data['totalItems']);
        $this->assertCount(50, $data['member']);
    }
}

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

/**
 * #873: the item route was wired to the collection provider, which only reads bandSpaceId, so it
 * answered every single-activity request with the whole paginated feed instead of the row asked for.
 */
#[ResetDatabase]
class BandSpaceActivityItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_member_reads_a_single_activity(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $wanted = BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Task,
            'type' => 'status_changed',
            'actor' => $admin,
            'payload' => ['from' => 'todo', 'to' => 'done'],
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();

        // A second row in the same space: the response must not carry it.
        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Finance,
            'type' => 'entry_created',
            'actor' => $admin,
            'payload' => ['label' => 'Studio'],
            'creationDatetime' => new \DateTime('2026-04-02 10:00:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities/' . $wanted->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceActivity',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/activities/' . $wanted->id,
            '@type' => 'BandSpaceActivity',
            'id' => $wanted->id,
            'band_space_id' => $bandSpace->id,
            'module' => 'task',
            'resource_id' => null,
            'type' => 'status_changed',
            'payload' => ['from' => 'todo', 'to' => 'done'],
            'actor' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'profile_picture_url' => null,
            ],
            'creation_datetime' => '2026-04-01T10:00:00+00:00',
        ]);
    }

    /**
     * #866 masking has to survive on this route too: the item DTO is built by BandSpaceActivityBuilder,
     * so the invitee address goes through ActivityPayloadMask exactly as it does in the feed.
     */
    public function test_an_invitee_email_is_masked_on_the_item_route(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $activity = BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Settings,
            'type' => 'invitation_sent',
            'actor' => $admin,
            'payload' => ['email' => 'john.doe@gmail.com', 'invited_user_id' => null, 'invited_username' => null],
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities/' . $activity->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceActivity',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/activities/' . $activity->id,
            '@type' => 'BandSpaceActivity',
            'id' => $activity->id,
            'band_space_id' => $bandSpace->id,
            'module' => 'settings',
            'resource_id' => null,
            'type' => 'invitation_sent',
            'payload' => [
                'email' => 'j***@gmail.com',
                'invited_user_id' => null,
                'invited_username' => null,
            ],
            'actor' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'profile_picture_url' => null,
            ],
            'creation_datetime' => '2026-04-01T10:00:00+00:00',
        ]);
        $this->assertStringNotContainsString('john.doe@gmail.com', (string) $this->client->getResponse()->getContent());
    }

    public function test_an_activity_of_another_band_space_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $mine = BandSpaceFactory::new()->create();
        $other = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $mine, 'user' => $user, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $other, 'user' => $user, 'role' => Role::Admin])->create();

        $foreign = BandSpaceActivityFactory::new([
            'bandSpace' => $other,
            'module' => BandSpaceModule::Settings,
            'type' => 'invitation_sent',
            'actor' => $user,
            'payload' => ['email' => 'john.doe@gmail.com', 'invited_user_id' => null, 'invited_username' => null],
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $mine->id . '/activities/' . $foreign->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Activité introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Activité introuvable',
        ]);
    }

    public function test_an_unknown_activity_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'role' => Role::Admin])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities/2fd0c2a2-6b02-4f14-8c8a-90e2a5b6a1d3',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Activité introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Activité introuvable',
        ]);
    }

    /**
     * A non-uuid id must not reach the uuid column, where it would raise ValueNotConvertible and 500.
     */
    public function test_a_malformed_activity_id_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'role' => Role::Admin])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities/not-a-uuid',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Activité introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Activité introuvable',
        ]);
    }

    public function test_non_member_forbidden(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner, 'role' => Role::Admin])->create();

        $activity = BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Task,
            'type' => 'status_changed',
            'actor' => $owner,
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities/' . $activity->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
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

    public function test_anonymous_unauthorized(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner, 'role' => Role::Admin])->create();

        $activity = BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Task,
            'type' => 'status_changed',
            'actor' => $owner,
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();

        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities/' . $activity->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }
}

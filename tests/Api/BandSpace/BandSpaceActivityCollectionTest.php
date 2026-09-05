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
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Regex;
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

    public function test_a_bare_calendar_day_is_refused_because_the_bounds_are_instants(): void
    {
        // The one pair of date parameters in the app that are instants rather than calendar days.
        // The picker is day granular, but only the client knows its own offset, so it is the client
        // that turns the picked day into an instant: a viewer in Paris asking for the 5th means
        // 22:00 UTC on the 4th. A server reading a bare `2026-02-01` as a UTC day would quietly clip
        // the first hours of the day they asked for, so the bare form is refused rather than guessed.
        // The shape half of the Sequentially pair is what catches it, before the calendar half runs.
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?from=2026-02-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from',
                    'message' => 'Cette valeur n\'est pas une date/heure valide.',
                    'code' => Regex::REGEX_FAILED_ERROR,
                ],
            ],
            'detail' => 'from: Cette valeur n\'est pas une date/heure valide.',
            'type' => '/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            'title' => 'An error occurred',
            'description' => 'from: Cette valeur n\'est pas une date/heure valide.',
        ]);
    }

    public function test_a_null_byte_bound_is_refused_rather_than_crashing(): void
    {
        // Regression for a 500 this endpoint had while its constraint was a bare
        // Assert\DateTime(format: ATOM). DateTimeValidator works by calling
        // DateTimeImmutable::createFromFormat(), which raises a ValueError on a null byte, and a
        // ValueError is not an Exception, so it escaped both Symfony's validator and
        // ParameterValidatorProvider. Worse, parameter validation runs before the operation's
        // security expression, so `?from=%00` was a 500 available to anyone. The Sequentially
        // wrapper puts an anchored regex in front, and a null byte cannot match it.
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?from=%00',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from',
                    'message' => 'Cette valeur n\'est pas une date/heure valide.',
                    'code' => Regex::REGEX_FAILED_ERROR,
                ],
            ],
            'detail' => 'from: Cette valeur n\'est pas une date/heure valide.',
            'type' => '/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            'title' => 'An error occurred',
            'description' => 'from: Cette valeur n\'est pas une date/heure valide.',
        ]);
    }

    public function test_an_impossible_instant_is_refused_by_the_calendar_check(): void
    {
        // The regex alone would let this through: it is the right shape and a nonsense instant, and
        // the provider's `new DateTimeImmutable()` would throw on it. Assert\DateTime is the second
        // half of the Sequentially pair and the reason the pair exists rather than a lone regex.
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities?from=2026-13-45T99:99:99Z',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . DateTime::INVALID_DATE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from',
                    'message' => 'Cette valeur n\'est pas une date/heure valide.',
                    'code' => DateTime::INVALID_DATE_ERROR,
                ],
            ],
            'detail' => 'from: Cette valeur n\'est pas une date/heure valide.',
            'type' => '/validation_errors/' . DateTime::INVALID_DATE_ERROR,
            'title' => 'An error occurred',
            'description' => 'from: Cette valeur n\'est pas une date/heure valide.',
        ]);
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

    public function test_non_admin_member_lists_activities(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Task,
            'type' => 'status_changed',
            'actor' => $admin,
        ])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/activities',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        // #726: regular members can now read the band space activity feed (was admin-only).
        $this->assertResponseIsSuccessful();
        $data = $this->getResponseAsArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertSame('status_changed', $data['member'][0]['type']);
    }

    /**
     * The pending-invitation list is admin only, this feed is open to every member, and both carry the
     * invitee's address. Masking happens when the stored row is turned into the response, so the rows
     * written in plaintext before the fix are covered too and the raw address never reaches the wire.
     */
    public function test_an_invitee_email_is_masked_for_a_plain_member_reading_the_feed(): void
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
            '/api/band_spaces/' . $bandSpace->id . '/activities',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceActivity',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/activities',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
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
                ],
            ],
        ]);
        // Belt and braces: the address must be absent from the whole body, not just from the field we
        // happened to look at. Masking in the frontend would still have shipped it here.
        $this->assertStringNotContainsString('john.doe@gmail.com', (string) $this->client->getResponse()->getContent());
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

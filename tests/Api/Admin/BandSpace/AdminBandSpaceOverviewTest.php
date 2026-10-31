<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The admin's view of how Band Spaces are used (#1275). */
#[ResetDatabase]
class AdminBandSpaceOverviewTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const int DEFAULT_QUOTA_BYTES = 5368709120;

    public function test_the_overview_needs_a_login(): void
    {
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_overview_is_refused_to_a_member(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
        ]);
    }

    public function test_the_overview_refuses_a_malformed_date(): void
    {
        $this->client->loginUser(UserFactory::new()->asAdminUser()->create());
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=2026-02-31&to=2026-09-30');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_DATE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from',
                    'message' => 'Cette valeur n\'est pas une date valide.',
                    'code' => Date::INVALID_DATE_ERROR,
                ],
            ],
            'detail' => 'from: Cette valeur n\'est pas une date valide.',
            'description' => 'from: Cette valeur n\'est pas une date valide.',
            'type' => '/validation_errors/' . Date::INVALID_DATE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_the_overview_needs_a_period(): void
    {
        $this->client->loginUser(UserFactory::new()->asAdminUser()->create());
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=2026-09-01');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'to',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'to: Cette valeur ne doit pas être vide.',
            'description' => 'to: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_the_overview_with_no_band_space(): void
    {
        $this->client->loginUser(UserFactory::new()->asAdminUser()->create());
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminBandSpaceOverview',
            '@id' => '/api/admin/band-spaces/overview',
            '@type' => 'AdminBandSpaceOverview',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'spaces' => 0,
            'pending_deletion_spaces' => 0,
            'active_spaces' => 0,
            'dormant_spaces' => 0,
            'active_window_days' => 30,
            'average_members' => 0,
            'median_members' => 0,
            'solo_spaces' => 0,
            'chat_messages' => 0,
            'chat_spaces' => 0,
            'storage_bytes' => 0,
            'modules' => $this->moduleUsage([]),
            'top_storage' => [],
        ]);
    }

    public function test_the_overview_leaves_the_spaces_pending_deletion_out(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $today = new \DateTimeImmutable();

        // Alpha: three active members and a former one, busy in the period.
        $alpha = BandSpaceFactory::new()->create(['name' => 'Alpha']);
        $this->addMembers($alpha, 3);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $alpha, 'status' => MembershipStatus::Left]);
        $this->addActivity($alpha, BandSpaceModule::Task, '-3 days');
        $this->addActivity($alpha, BandSpaceModule::File, '-2 days');
        $this->addActivity($alpha, BandSpaceModule::File, 'now');
        $this->addChatMessages($alpha, 2, '-4 days');
        $this->addFile($alpha, [1000, 500]);
        $this->addFile($alpha, [9999], archived: true);

        // Bravo: alone, quiet for two months, with a quota of its own.
        $bravo = BandSpaceFactory::new()->create(['name' => 'Bravo', 'quotaBytesOverride' => 10000]);
        $this->addMembers($bravo, 1);
        $this->addActivity($bravo, BandSpaceModule::Notes, '-60 days');
        $this->addFile($bravo, [3000]);

        // Delta: two members, active through its chat only.
        $delta = BandSpaceFactory::new()->create(['name' => 'Delta']);
        $this->addMembers($delta, 2);
        $this->addChatMessages($delta, 1, '-5 days');

        // Echo: active through an activity and a message older than the period, so counted in neither.
        $echo = BandSpaceFactory::new()->create(['name' => 'Echo']);
        $this->addMembers($echo, 2);
        $this->addActivity($echo, BandSpaceModule::Setlist, '-20 days');
        $this->addChatMessages($echo, 1, '-20 days');

        // Charlie: pending deletion, counted on its own and nowhere else.
        $charlie = BandSpaceFactory::new()->create([
            'name' => 'Charlie',
            'deletionScheduledDatetime' => new \DateTimeImmutable('+20 days'),
        ]);
        $this->addMembers($charlie, 1);
        $this->addActivity($charlie, BandSpaceModule::Agenda, '-1 day');
        $this->addChatMessages($charlie, 4, '-1 day');
        $this->addFile($charlie, [50000]);

        $from = $today->modify('-10 days')->format('Y-m-d');
        $to = $today->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=' . $from . '&to=' . $to);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminBandSpaceOverview',
            '@id' => '/api/admin/band-spaces/overview',
            '@type' => 'AdminBandSpaceOverview',
            'from' => $from,
            'to' => $to,
            'spaces' => 4,
            'pending_deletion_spaces' => 1,
            'active_spaces' => 3,
            'dormant_spaces' => 1,
            'active_window_days' => 30,
            'average_members' => 2,
            'median_members' => 2,
            'solo_spaces' => 1,
            'chat_messages' => 3,
            'chat_spaces' => 2,
            'storage_bytes' => 4500,
            'modules' => $this->moduleUsage([
                'file' => ['spaces' => 1, 'activities' => 2],
                'task' => ['spaces' => 1, 'activities' => 1],
            ]),
            'top_storage' => [
                ['@type' => 'AdminBandSpaceStorage', 'name' => 'Bravo', 'bytes' => 3000, 'quota_bytes' => 10000],
                ['@type' => 'AdminBandSpaceStorage', 'name' => 'Alpha', 'bytes' => 1500, 'quota_bytes' => self::DEFAULT_QUOTA_BYTES],
            ],
        ]);
    }

    public function test_the_member_figures_average_the_spaces_and_take_the_middle_two_for_the_median(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        foreach ([1, 1, 2, 5] as $members) {
            $this->addMembers(BandSpaceFactory::new()->create(), $members);
        }

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/band-spaces/overview?from=2026-09-01&to=2026-09-30');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminBandSpaceOverview',
            '@id' => '/api/admin/band-spaces/overview',
            '@type' => 'AdminBandSpaceOverview',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'spaces' => 4,
            'pending_deletion_spaces' => 0,
            'active_spaces' => 0,
            'dormant_spaces' => 4,
            'active_window_days' => 30,
            'average_members' => 2.3,
            'median_members' => 1.5,
            'solo_spaces' => 2,
            'chat_messages' => 0,
            'chat_spaces' => 0,
            'storage_bytes' => 0,
            'modules' => $this->moduleUsage([]),
            'top_storage' => [],
        ]);
    }

    /**
     * @param array<string, array{spaces: int, activities: int}> $used
     *
     * @return list<array<string, int|string>>
     */
    private function moduleUsage(array $used): array
    {
        return array_map(static fn (BandSpaceModule $module): array => [
            '@type' => 'AdminBandSpaceModuleUsage',
            'module' => $module->value,
            'spaces' => $used[$module->value]['spaces'] ?? 0,
            'activities' => $used[$module->value]['activities'] ?? 0,
        ], BandSpaceModule::cases());
    }

    private function addMembers(BandSpace $bandSpace, int $count): void
    {
        BandSpaceMembershipFactory::new()->many($count)->create(['bandSpace' => $bandSpace]);
    }

    private function addActivity(BandSpace $bandSpace, BandSpaceModule $module, string $when): void
    {
        BandSpaceActivityFactory::new()->create([
            'bandSpace' => $bandSpace,
            'module' => $module,
            'creationDatetime' => new \DateTime($when),
        ]);
    }

    private function addChatMessages(BandSpace $bandSpace, int $count, string $when): void
    {
        $channel = MessageThreadFactory::new()->create(['bandSpace' => $bandSpace, 'name' => 'general']);
        MessageFactory::new()->many($count)->create(['thread' => $channel, 'creationDatetime' => new \DateTime($when)]);
    }

    /** @param list<int> $versionSizes */
    private function addFile(BandSpace $bandSpace, array $versionSizes, bool $archived = false): void
    {
        $file = BandSpaceFileFactory::new()->create([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => $archived ? new \DateTimeImmutable('-1 day') : null,
        ]);
        foreach ($versionSizes as $index => $size) {
            BandSpaceFileVersionFactory::new()->create(['bandSpaceFile' => $file, 'versionNumber' => $index + 1, 'size' => $size]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Enum\User\ClientPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserDailyActivityFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PlatformActivityMetricsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    public function test_it_counts_distinct_members_per_platform_and_the_versions_of_the_last_30_days(): void
    {
        self::mockTime('2026-11-11 10:00:00');
        $admin = UserFactory::new()->asAdminUser()->create();
        $webOnly = UserFactory::new()->create(['registrationPlatform' => ClientPlatform::Web]);
        $appAndWeb = UserFactory::new()->create(['registrationPlatform' => ClientPlatform::Ios]);
        $oldBuild = UserFactory::new()->create(['registrationPlatform' => ClientPlatform::UnknownApp]);
        $beforeTheRange = UserFactory::new()->create(['registrationPlatform' => ClientPlatform::Android]);
        UserDailyActivityFactory::new()->create(['user' => $webOnly, 'day' => new \DateTimeImmutable('2026-11-01')]);
        UserDailyActivityFactory::new()->create(['user' => $webOnly, 'day' => new \DateTimeImmutable('2026-11-02')]);
        UserDailyActivityFactory::new()->create(['user' => $appAndWeb, 'day' => new \DateTimeImmutable('2026-11-01'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.14.0']);
        UserDailyActivityFactory::new()->create(['user' => $appAndWeb, 'day' => new \DateTimeImmutable('2026-11-05')]);
        UserDailyActivityFactory::new()->create(['user' => $oldBuild, 'day' => new \DateTimeImmutable('2026-11-03'), 'platform' => ClientPlatform::UnknownApp]);
        // Before the range but within the last 30 days: not active over the range, its version still in use.
        UserDailyActivityFactory::new()->create(['user' => $beforeTheRange, 'day' => new \DateTimeImmutable('2026-10-20'), 'platform' => ClientPlatform::Android, 'appVersion' => '1.9.0']);
        // Older than 30 days: that version is no longer in use.
        UserDailyActivityFactory::new()->create(['user' => $beforeTheRange, 'day' => new \DateTimeImmutable('2026-10-01'), 'platform' => ClientPlatform::Android, 'appVersion' => '1.0.0']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/platform-activity?from=2026-11-01&to=2026-11-10');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PlatformActivityMetrics',
            '@id' => '/api/admin/dashboard/platform-activity',
            '@type' => 'PlatformActivityMetrics',
            'active_members' => 3,
            'daily_active_members' => [
                ['date_label' => '2026-11-01', 'segment' => 'ios', 'count' => 1],
                ['date_label' => '2026-11-01', 'segment' => 'web', 'count' => 1],
                ['date_label' => '2026-11-02', 'segment' => 'web', 'count' => 1],
                ['date_label' => '2026-11-03', 'segment' => 'unknown_app', 'count' => 1],
                ['date_label' => '2026-11-05', 'segment' => 'web', 'count' => 1],
            ],
            'active_members_by_platform' => ['web' => 2, 'ios' => 1, 'android' => 0, 'unknown_app' => 1],
            'multi_platform_members' => 1,
            'active_members_by_registration_platform' => ['web' => 1, 'ios' => 1, 'android' => 0, 'unknown_app' => 1],
            'app_versions' => [
                ['platform' => 'android', 'version' => '1.9.0', 'members' => 1],
                ['platform' => 'ios', 'version' => '1.14.0', 'members' => 1],
                ['platform' => 'unknown_app', 'version' => null, 'members' => 1],
            ],
        ]);
    }

    public function test_it_needs_to_be_logged_in(): void
    {
        $this->client->request('GET', '/api/admin/dashboard/platform-activity?from=2026-11-01&to=2026-11-10');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_it_is_for_admins_only(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/admin/dashboard/platform-activity?from=2026-11-01&to=2026-11-10');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Access Denied. The user doesn\'t have ROLE_ADMIN.',
            'description' => 'Access Denied. The user doesn\'t have ROLE_ADMIN.',
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_it_needs_a_period(): void
    {
        $this->client->loginUser(UserFactory::new()->asAdminUser()->create());
        $this->client->request('GET', '/api/admin/dashboard/platform-activity?to=2026-11-10');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'from',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'from: Cette valeur ne doit pas être vide.',
            'description' => 'from: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }
}

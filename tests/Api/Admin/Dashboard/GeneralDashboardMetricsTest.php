<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class GeneralDashboardMetricsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_general_dashboard_metrics_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/GeneralDashboardMetrics',
            '@id' => '/api/admin/dashboard/general',
            '@type' => 'GeneralDashboardMetrics',
            'dau_mau_ratio' => 100,
            'total_users' => 1,
            'total_publications' => 0,
            'total_messages' => 0,
        ]);
    }

    /** A refresh token renews itself for months without a login, so a member counts as active by their activity (#1024). */
    public function test_daily_and_monthly_actives_count_members_by_when_they_were_last_seen(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        // Active today, last signed in months ago: a daily active
        UserFactory::new()->create([
            'lastLoginDatetime' => new \DateTime('-90 days'),
            'lastActivityDatetime' => new \DateTimeImmutable(),
        ]);
        // No activity ever recorded: the last login stands in, a monthly active only
        UserFactory::new()->create([
            'lastLoginDatetime' => new \DateTime('-10 days'),
            'lastActivityDatetime' => null,
        ]);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/GeneralDashboardMetrics',
            '@id' => '/api/admin/dashboard/general',
            '@type' => 'GeneralDashboardMetrics',
            'dau_mau_ratio' => 66.7,
            'total_users' => 1,
            'total_publications' => 0,
            'total_messages' => 0,
        ]);
    }

    public function test_retention_counts_a_member_seen_again_without_signing_in_again(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $registrationDay = new \DateTime('-7 days noon');
        // Signed in when registering, seen the next day on the same session: retained
        UserFactory::new()->create([
            'creationDatetime' => clone $registrationDay,
            'confirmationDatetime' => clone $registrationDay,
            'lastLoginDatetime' => clone $registrationDay,
            'lastActivityDatetime' => new \DateTimeImmutable('-6 days'),
        ]);
        // Never seen after registering: not retained
        UserFactory::new()->create([
            'creationDatetime' => clone $registrationDay,
            'confirmationDatetime' => clone $registrationDay,
            'lastLoginDatetime' => clone $registrationDay,
            'lastActivityDatetime' => null,
        ]);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/GeneralDashboardMetrics',
            '@id' => '/api/admin/dashboard/general',
            '@type' => 'GeneralDashboardMetrics',
            'dau_mau_ratio' => 33.3,
            'retention7_days' => 50,
            'total_users' => 3,
            'total_publications' => 0,
            'total_messages' => 0,
        ]);
    }

    public function test_get_general_dashboard_metrics_not_logged(): void
    {
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_general_dashboard_metrics_as_normal_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

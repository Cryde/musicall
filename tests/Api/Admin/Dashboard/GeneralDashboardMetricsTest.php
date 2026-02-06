<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GeneralDashboardMetricsTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_general_dashboard_metrics_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

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
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

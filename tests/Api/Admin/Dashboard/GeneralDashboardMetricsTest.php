<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
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
            'registrations_today' => 0,
            'registrations7_days' => 0,
            'registrations30_days' => 0,
            'logins_today' => 1,
            'logins7_days' => 1,
            'logins30_days' => 1,
            'logins_trend_percent' => 100,
            'messages_today' => 0,
            'messages7_days' => 0,
            'messages30_days' => 0,
            'publications_today' => 0,
            'publications7_days' => 0,
            'publications30_days' => 0,
            'dau_mau_ratio' => 100,
            'publications_by_type' => [],
            'top_content_this_week' => [],
            'total_users' => 1,
            'total_publications' => 0,
            'total_messages' => 0,
        ]);
    }

    public function test_get_general_dashboard_metrics_with_data(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        // Create a user registered today
        UserFactory::new()->with([
            'creationDatetime' => new \DateTime(),
            'lastLoginDatetime' => new \DateTime(),
            'email' => 'today@test.com',
            'username' => 'today_user',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime(),
        ])->create();

        // Create a publication
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $viewCache = ViewCacheFactory::new(['count' => 50])->create();
        $publication = PublicationFactory::new([
            'author' => $admin,
            'title' => 'Test Publication',
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => $sub,
            'publicationDatetime' => new \DateTime(),
            'viewCache' => $viewCache,
        ])->create()->_real();

        // Create a message
        $thread = MessageThreadFactory::new()->create();
        MessageFactory::new([
            'author' => $admin,
            'thread' => $thread,
            'creationDatetime' => new \DateTime(),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/general');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/GeneralDashboardMetrics',
            '@id' => '/api/admin/dashboard/general',
            '@type' => 'GeneralDashboardMetrics',
            'registrations_today' => 1,
            'registrations7_days' => 1,
            'registrations30_days' => 1,
            'registrations_trend_percent' => 100,
            'logins_today' => 2,
            'logins7_days' => 2,
            'logins30_days' => 2,
            'logins_trend_percent' => 100,
            'messages_today' => 1,
            'messages7_days' => 1,
            'messages30_days' => 1,
            'messages_trend_percent' => 100,
            'publications_today' => 1,
            'publications7_days' => 1,
            'publications30_days' => 1,
            'publications_trend_percent' => 100,
            'dau_mau_ratio' => 100,
            'publications_by_type' => [
                'chroniques' => 1,
            ],
            'top_content_this_week' => [
                [
                    'id' => $publication->getId(),
                    'title' => 'Test Publication',
                    'views' => 50,
                    'type' => 'text',
                ],
            ],
            'total_users' => 2,
            'total_publications' => 1,
            'total_messages' => 1,
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

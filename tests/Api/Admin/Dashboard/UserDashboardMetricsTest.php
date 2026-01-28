<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserDashboardMetricsTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_user_dashboard_metrics_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => [],
            'profile_completion_rates' => [
                'last_7_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
                'last_30_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
            ],
            'top_contributors' => [],
            'recent_registrations' => [
                [
                    'id' => $admin->getId(),
                    'username' => 'user_admin',
                    'email' => 'admin@email.com',
                    'registration_date' => '1990-01-02 02:03',
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
            ],
            'top_messagers' => [],
            'total_users_last24h' => 0,
            'total_users_last7_days' => 0,
            'unconfirmed_accounts' => 0,
        ]);
    }

    public function test_get_user_dashboard_metrics_with_recent_registrations(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $user1 = UserFactory::new()->with([
            'creationDatetime' => new \DateTime('2024-01-15 10:00:00'),
            'email' => 'today1@test.com',
            'username' => 'today_user_1',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime('2024-01-15 10:00:00'),
        ])->create()->_real();

        $user2 = UserFactory::new()->with([
            'creationDatetime' => new \DateTime('2024-01-15 11:00:00'),
            'email' => 'today2@test.com',
            'username' => 'today_user_2',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime('2024-01-15 11:00:00'),
        ])->create()->_real();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => [],
            'profile_completion_rates' => [
                'last_7_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
                'last_30_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
            ],
            'top_contributors' => [],
            'recent_registrations' => [
                [
                    'id' => $user2->getId(),
                    'username' => 'today_user_2',
                    'email' => 'today2@test.com',
                    'registration_date' => '2024-01-15 11:00',
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
                [
                    'id' => $user1->getId(),
                    'username' => 'today_user_1',
                    'email' => 'today1@test.com',
                    'registration_date' => '2024-01-15 10:00',
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
                [
                    'id' => $admin->getId(),
                    'username' => 'user_admin',
                    'email' => 'admin@email.com',
                    'registration_date' => '1990-01-02 02:03',
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
            ],
            'top_messagers' => [],
            'total_users_last24h' => 0,
            'total_users_last7_days' => 0,
            'unconfirmed_accounts' => 0,
        ]);
    }

    public function test_get_user_dashboard_metrics_with_top_messagers(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $registrationDate = new \DateTime('-30 days');
        $messager = UserFactory::new()->with([
            'creationDatetime' => $registrationDate,
            'email' => 'messager@test.com',
            'username' => 'top_messager',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => $registrationDate,
        ])->create()->_real();

        $thread = MessageThreadFactory::new()->create();
        for ($i = 0; $i < 5; $i++) {
            MessageFactory::new([
                'author' => $messager,
                'thread' => $thread,
                'creationDatetime' => new \DateTime('-' . $i . ' days'),
            ])->create();
        }

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => [],
            'profile_completion_rates' => [
                'last_7_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
                'last_30_days' => [
                    'avg_percent' => 0.0,
                    'total' => 1,
                    'levels' => [
                        'empty' => 1,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
            ],
            'top_contributors' => [],
            'recent_registrations' => [
                [
                    'id' => $messager->getId(),
                    'username' => 'top_messager',
                    'email' => 'messager@test.com',
                    'registration_date' => $registrationDate->format('Y-m-d H:i'),
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
                [
                    'id' => $admin->getId(),
                    'username' => 'user_admin',
                    'email' => 'admin@email.com',
                    'registration_date' => '1990-01-02 02:03',
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
            ],
            'top_messagers' => [
                [
                    'id' => $messager->getId(),
                    'username' => 'top_messager',
                    'message_count' => 5,
                    'account_age_days' => 30,
                    'avg_messages_per_day' => 0.7,
                ],
            ],
            'total_users_last24h' => 0,
            'total_users_last7_days' => 0,
            'unconfirmed_accounts' => 0,
        ]);
    }

    public function test_get_user_dashboard_metrics_with_unconfirmed_accounts(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        // Create an unconfirmed user (no confirmationDatetime, has token)
        // Note: unconfirmed users don't appear in recent_registrations (which only shows confirmed users)
        UserFactory::new()->with([
            'creationDatetime' => new \DateTime('2024-01-10 09:00:00'),
            'email' => 'unconfirmed@test.com',
            'username' => 'unconfirmed_user',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => null,
            'token' => 'test-confirmation-token',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => [],
            'profile_completion_rates' => [
                'last_7_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
                'last_30_days' => [
                    'avg_percent' => 0.0,
                    'total' => 0,
                    'levels' => [
                        'empty' => 0,
                        'basic' => 0,
                        'complete' => 0,
                    ],
                ],
            ],
            'top_contributors' => [],
            'recent_registrations' => [
                [
                    'id' => $admin->getId(),
                    'username' => 'user_admin',
                    'email' => 'admin@email.com',
                    'registration_date' => '1990-01-02 02:03',
                    'profile_completion_percent' => 0,
                    'first_action' => null,
                ],
            ],
            'top_messagers' => [],
            'total_users_last24h' => 0,
            'total_users_last7_days' => 0,
            'unconfirmed_accounts' => 1,
        ]);
    }

    public function test_get_user_dashboard_metrics_not_logged(): void
    {
        $this->client->request('GET', '/api/admin/dashboard/users');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_user_dashboard_metrics_as_normal_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/users');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserEmailLogFactory;
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
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => [],
            'profile_completion_rates' => [
                'avg_percent' => 0,
                'total' => 0,
                'levels' => [
                    'empty' => 0,
                    'basic' => 0,
                    'complete' => 0,
                ],
            ],
            'recent_registrations' => [],
            'top_messagers' => [],
            'total_users' => 1,
            'unconfirmed_accounts' => 0,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [],
        ]);
    }

    public function test_get_user_dashboard_metrics_with_recent_registrations(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $user1 = UserFactory::new()->with([
            'creationDatetime' => new \DateTime(),
            'email' => 'today1@test.com',
            'username' => 'today_user_1',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime(),
        ])->create()->_real();

        $user2 = UserFactory::new()->with([
            'creationDatetime' => new \DateTime(),
            'email' => 'today2@test.com',
            'username' => 'today_user_2',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime(),
        ])->create()->_real();

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['recent_registrations']);
        $usernames = array_column($response['recent_registrations'], 'username');
        $this->assertContains('today_user_1', $usernames);
        $this->assertContains('today_user_2', $usernames);
        $this->assertSame(3, $response['total_users']);
    }

    public function test_get_user_dashboard_metrics_with_top_messagers(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $messager = UserFactory::new()->with([
            'creationDatetime' => new \DateTime('-30 days'),
            'email' => 'messager@test.com',
            'username' => 'top_messager',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime('-30 days'),
        ])->create()->_real();

        $thread = MessageThreadFactory::new()->create();
        for ($i = 0; $i < 5; $i++) {
            MessageFactory::new([
                'author' => $messager,
                'thread' => $thread,
                'creationDatetime' => new \DateTime('-' . $i . ' days'),
            ])->create();
        }

        $thirtyDaysAgo = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $thirtyDaysAgo . '&to=' . $today);
        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response['top_messagers']);
        $this->assertSame('top_messager', $response['top_messagers'][0]['username']);
        $this->assertSame(5, $response['top_messagers'][0]['message_count']);
    }

    public function test_get_user_dashboard_metrics_with_unconfirmed_accounts(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        UserFactory::new()->with([
            'creationDatetime' => new \DateTime('2024-01-10 09:00:00'),
            'email' => 'unconfirmed@test.com',
            'username' => 'unconfirmed_user',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => null,
            'token' => 'test-confirmation-token',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $response['unconfirmed_accounts']);
        $this->assertSame(1, $response['total_users']);
    }

    public function test_get_user_dashboard_metrics_with_emails_sent(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        // 2 welcome emails + 1 inactivity reminder today
        UserEmailLogFactory::new()->welcome()->with(['sentDatetime' => new \DateTimeImmutable()])->create();
        UserEmailLogFactory::new()->welcome()->with(['sentDatetime' => new \DateTimeImmutable()])->create();
        UserEmailLogFactory::new()->inactivityReminder()->with(['sentDatetime' => new \DateTimeImmutable()])->create();
        // 1 email outside range (should be excluded)
        UserEmailLogFactory::new()->welcome()->with(['sentDatetime' => new \DateTimeImmutable('-10 days')])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => [],
            'profile_completion_rates' => [
                'avg_percent' => 0,
                'total' => 0,
                'levels' => [
                    'empty' => 0,
                    'basic' => 0,
                    'complete' => 0,
                ],
            ],
            'recent_registrations' => [],
            'top_messagers' => [],
            'total_users' => 1,
            'unconfirmed_accounts' => 0,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [
                'welcome' => 2,
                'inactivity_reminder' => 1,
            ],
        ]);
    }

    public function test_get_user_dashboard_metrics_not_logged(): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_user_dashboard_metrics_as_normal_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_user_dashboard_metrics_missing_from(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_get_user_dashboard_metrics_missing_to(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

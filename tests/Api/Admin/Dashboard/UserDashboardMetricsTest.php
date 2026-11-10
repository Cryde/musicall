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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserDashboardMetricsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_user_dashboard_metrics_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
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
            'registration_campaigns' => [],
            'recent_teachers' => [],
            'total_users' => 1,
            'unconfirmed_accounts' => 0,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [],
        ]);
    }

    public function test_get_user_dashboard_metrics_with_recent_registrations(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $user1 = UserFactory::new()->with([
            'creationDatetime' => new \DateTime(),
            'email' => 'today1@test.com',
            'username' => 'today_user_1',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime(),
        ])->create();

        $user2 = UserFactory::new()->with([
            'creationDatetime' => new \DateTime(),
            'email' => 'today2@test.com',
            'username' => 'today_user_2',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime(),
        ])->create();

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => $response['recent_empty_accounts'],
            'profile_completion_rates' => $response['profile_completion_rates'],
            'recent_registrations' => $response['recent_registrations'],
            'top_messagers' => [],
            'registration_campaigns' => [],
            'recent_teachers' => [],
            'total_users' => 3,
            'unconfirmed_accounts' => 0,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [],
        ]);
        $this->assertCount(2, $response['recent_registrations']);
        $usernames = array_column($response['recent_registrations'], 'username');
        $this->assertContains('today_user_1', $usernames);
        $this->assertContains('today_user_2', $usernames);
    }

    public function test_get_user_dashboard_metrics_with_top_messagers(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $messager = UserFactory::new()->with([
            'creationDatetime' => new \DateTime('-30 days'),
            'email' => 'messager@test.com',
            'username' => 'top_messager',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime('-30 days'),
        ])->create();

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
        $response = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => $response['recent_empty_accounts'],
            'profile_completion_rates' => $response['profile_completion_rates'],
            'recent_registrations' => $response['recent_registrations'],
            'top_messagers' => [
                [
                    'id' => $messager->id,
                    'username' => 'top_messager',
                    'message_count' => 5,
                    'account_age_days' => 30,
                    'avg_messages_per_day' => 0.2,
                ],
            ],
            'registration_campaigns' => [],
            'recent_teachers' => [],
            'total_users' => 2,
            'unconfirmed_accounts' => 0,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [],
        ]);
    }

    public function test_get_user_dashboard_metrics_with_unconfirmed_accounts(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
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
            'registration_campaigns' => [],
            'recent_teachers' => [],
            'total_users' => 1,
            'unconfirmed_accounts' => 1,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [],
        ]);
    }

    public function test_get_user_dashboard_metrics_with_emails_sent(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
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
            'registration_campaigns' => [],
            'recent_teachers' => [],
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

    /** The campaigns of the range (#1369), most sign-ups first; a sign-up without any utm_* is left out. */
    public function test_get_user_dashboard_metrics_with_registration_campaigns(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $musiciens = ['registrationUtmCampaign' => 'pmax-musiciens', 'registrationUtmSource' => 'google', 'registrationUtmMedium' => 'cpc'];

        $this->createUserRegisteredOn(new \DateTime(), $musiciens);
        $this->createUserRegisteredOn(new \DateTime(), [...$musiciens, 'registrationReferrerHost' => 'www.google.com']);
        $this->createUserRegisteredOn(new \DateTime(), ['registrationUtmCampaign' => 'pmax-band-space', 'registrationUtmSource' => 'google', 'registrationUtmMedium' => 'cpc']);
        $this->createUserRegisteredOn(new \DateTime(), ['registrationUtmSource' => 'newsletter', 'registrationUtmMedium' => 'email']);
        // Neither a campaign nor any utm_*: not in the table
        $this->createUserRegisteredOn(new \DateTime(), ['registrationReferrerHost' => 'www.facebook.com']);
        $this->createUserRegisteredOn(new \DateTime(), []);
        // Outside the range
        $this->createUserRegisteredOn(new \DateTime('-10 days'), $musiciens);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserDashboardMetrics',
            '@id' => '/api/admin/dashboard/users',
            '@type' => 'UserDashboardMetrics',
            'recent_empty_accounts' => $response['recent_empty_accounts'],
            'profile_completion_rates' => $response['profile_completion_rates'],
            'recent_registrations' => $response['recent_registrations'],
            'top_messagers' => [],
            'registration_campaigns' => [
                ['campaign' => 'pmax-musiciens', 'source' => 'google', 'medium' => 'cpc', 'count' => 2],
                ['campaign' => null, 'source' => 'newsletter', 'medium' => 'email', 'count' => 1],
                ['campaign' => 'pmax-band-space', 'source' => 'google', 'medium' => 'cpc', 'count' => 1],
            ],
            'recent_teachers' => [],
            'total_users' => 8,
            'unconfirmed_accounts' => 0,
            'total_musician_profiles' => 0,
            'total_teacher_profiles' => 0,
            'emails_sent_by_type' => [],
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
        $user = UserFactory::new()->asBaseUser()->create();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_user_dashboard_metrics_missing_from(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_get_user_dashboard_metrics_missing_to(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/users?from=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @param array<string, ?string> $campaign */
    private function createUserRegisteredOn(\DateTime $creationDatetime, array $campaign): void
    {
        UserFactory::new()->create(['creationDatetime' => $creationDatetime, 'confirmationDatetime' => $creationDatetime, ...$campaign]);
    }
}

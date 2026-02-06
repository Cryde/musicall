<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Entity\Musician\MusicianAnnounce;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TimeSeriesMetricsTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_time_series_registrations(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $today = new \DateTimeImmutable();
        $todayStr = $today->format('Y-m-d');

        // Create a user registered today
        UserFactory::new()->with([
            'creationDatetime' => new \DateTime(),
            'email' => 'today@test.com',
            'username' => 'today_user',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'confirmationDatetime' => new \DateTime(),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=registrations&from=' . $todayStr . '&to=' . $todayStr);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TimeSeriesMetrics',
            '@id' => '/api/admin/dashboard/time-series',
            '@type' => 'TimeSeriesMetrics',
            'metric' => 'registrations',
            'from' => $todayStr,
            'to' => $todayStr,
            'data_points' => [
                [
                    'date_label' => $todayStr,
                    'count' => 1,
                ],
            ],
            'total' => 1,
        ]);
    }

    public function test_get_time_series_comments(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $today = new \DateTimeImmutable();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $today->modify('-1 day')->format('Y-m-d');

        // 2 comments today
        CommentFactory::new(['creationDatetime' => new \DateTime()])->create();
        CommentFactory::new(['creationDatetime' => new \DateTime()])->create();
        // 1 comment yesterday (in range)
        CommentFactory::new(['creationDatetime' => new \DateTime('-1 day')])->create();
        // 1 comment outside range (should be excluded)
        CommentFactory::new(['creationDatetime' => new \DateTime('-10 days')])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=comments&from=' . $yesterdayStr . '&to=' . $todayStr);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TimeSeriesMetrics',
            '@id' => '/api/admin/dashboard/time-series',
            '@type' => 'TimeSeriesMetrics',
            'metric' => 'comments',
            'from' => $yesterdayStr,
            'to' => $todayStr,
            'data_points' => [
                ['date_label' => $yesterdayStr, 'count' => 1],
                ['date_label' => $todayStr, 'count' => 2],
            ],
            'total' => 3,
        ]);
    }

    public function test_get_time_series_forum_posts(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $today = new \DateTimeImmutable();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $today->modify('-1 day')->format('Y-m-d');

        // 3 posts today
        ForumPostFactory::new(['creationDatetime' => new \DateTime()])->create();
        ForumPostFactory::new(['creationDatetime' => new \DateTime()])->create();
        ForumPostFactory::new(['creationDatetime' => new \DateTime()])->create();
        // 1 post outside range (should be excluded)
        ForumPostFactory::new(['creationDatetime' => new \DateTime('-5 days')])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=forum_posts&from=' . $yesterdayStr . '&to=' . $todayStr);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TimeSeriesMetrics',
            '@id' => '/api/admin/dashboard/time-series',
            '@type' => 'TimeSeriesMetrics',
            'metric' => 'forum_posts',
            'from' => $yesterdayStr,
            'to' => $todayStr,
            'data_points' => [
                ['date_label' => $todayStr, 'count' => 3],
            ],
            'total' => 3,
        ]);
    }

    public function test_get_time_series_musician_announces(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $today = new \DateTimeImmutable();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $today->modify('-1 day')->format('Y-m-d');

        // 1 musician announce today + 1 band announce today (both count)
        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime(),
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
        ])->create();
        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime(),
            'type' => MusicianAnnounce::TYPE_BAND,
        ])->create();
        // 1 outside range (should be excluded)
        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime('-7 days'),
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=musician_announces&from=' . $yesterdayStr . '&to=' . $todayStr);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TimeSeriesMetrics',
            '@id' => '/api/admin/dashboard/time-series',
            '@type' => 'TimeSeriesMetrics',
            'metric' => 'musician_announces',
            'from' => $yesterdayStr,
            'to' => $todayStr,
            'data_points' => [
                ['date_label' => $todayStr, 'count' => 2],
            ],
            'total' => 2,
        ]);
    }

    public function test_get_time_series_not_logged(): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=registrations&from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_time_series_as_normal_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=registrations&from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_time_series_missing_metric(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/time-series?from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_get_time_series_invalid_metric(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/time-series?metric=invalid&from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Entity\User;
use App\Entity\User\UserDailyActivity;
use App\Enum\User\ClientPlatform;
use App\EventSubscriber\UserActivityListener;
use App\Repository\User\UserDailyActivityRepository;
use App\Repository\UserRepository;
use App\Tests\Factory\User\UserDailyActivityFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Listener is disabled globally in test env (see services.yaml when@test:)
 * so the suite doesn't pay an UPDATE-per-request; this test exercises it
 * directly to verify the throttle, the auth gating and the platform it records.
 */
#[ResetDatabase]
class UserActivityListenerTest extends KernelTestCase
{
    private const string NOW = '2026-11-11 10:00:00';
    private const array IOS_APP = ['HTTP_AUTHORIZATION' => 'Bearer a.b.c', 'HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => '1.14.0'];

    public function test_a_first_web_request_stamps_the_account_and_records_the_day(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener($user);

        $this->assertSame([self::NOW, ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    public function test_an_app_request_records_its_platform_and_version(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener($user, server: self::IOS_APP);

        $this->assertSame([self::NOW, ClientPlatform::Ios, '1.14.0'], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'ios', '1.14.0']], $this->rowsOf($user));
    }

    public function test_a_token_without_the_headers_is_recorded_as_an_unknown_app(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener($user, server: ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);

        $this->assertSame([self::NOW, ClientPlatform::UnknownApp, null], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'unknown_app', null]], $this->rowsOf($user));
    }

    /** The app's image requests carry its token but not its headers: they must not look like an old build. */
    public function test_a_header_less_request_from_a_member_on_an_app_keeps_that_app(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-11 09:50:00'), 'lastPlatform' => ClientPlatform::Ios, 'lastAppVersion' => '1.14.0']);

        $this->invokeListener($user, server: ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);

        $this->assertSame([self::NOW, ClientPlatform::Ios, '1.14.0'], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'ios', '1.14.0']], $this->rowsOf($user));
    }

    public function test_a_header_less_request_on_a_new_day_records_the_app_the_member_is_on(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-10 22:00:00'), 'lastPlatform' => ClientPlatform::Android, 'lastAppVersion' => '1.14.0']);

        $this->invokeListener($user, server: ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);

        $this->assertSame([['2026-11-11', 'android', '1.14.0']], $this->rowsOf($user));
    }

    public function test_invalid_headers_are_ignored(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener($user, server: ['HTTP_X_CLIENT_PLATFORM' => 'windows', 'HTTP_X_CLIENT_VERSION' => "1.13.0\n"]);

        $this->assertSame([self::NOW, ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    public function test_another_write_on_the_same_day_keeps_one_row(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-11 09:50:00'), 'lastPlatform' => ClientPlatform::Web]);
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2026-11-11')]);

        $this->invokeListener($user);

        $this->assertSame([self::NOW, ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    public function test_an_app_update_during_the_day_updates_its_row(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-11 09:50:00'), 'lastPlatform' => ClientPlatform::Ios, 'lastAppVersion' => '1.13.0']);
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2026-11-11'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.13.0']);

        $this->invokeListener($user, server: self::IOS_APP);

        $this->assertSame([self::NOW, ClientPlatform::Ios, '1.14.0'], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'ios', '1.14.0']], $this->rowsOf($user));
    }

    public function test_switching_platform_during_the_day_adds_a_row(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-11 09:50:00'), 'lastPlatform' => ClientPlatform::Web]);
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2026-11-11')]);

        $this->invokeListener($user, server: self::IOS_APP);

        $this->assertSame([['2026-11-11', 'ios', '1.14.0'], ['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    public function test_a_new_day_on_the_same_platform_adds_a_row(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-10 23:00:00'), 'lastPlatform' => ClientPlatform::Web]);
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2026-11-10')]);

        $this->invokeListener($user);

        $this->assertSame([['2026-11-10', 'web', null], ['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    /** Thirty seconds after the last write, but on a new day: the throttle must not hide that day. */
    public function test_a_new_day_is_recorded_even_inside_the_throttle_window(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-10 23:59:50'), 'lastPlatform' => ClientPlatform::Web]);

        $this->invokeListener($user, now: '2026-11-11 00:00:20');

        $this->assertSame(['2026-11-11 00:00:20', ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    public function test_nothing_is_written_inside_the_throttle_window(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-11 09:59:30'), 'lastPlatform' => ClientPlatform::Web]);

        $this->invokeListener($user, server: self::IOS_APP);

        $this->assertSame(['2026-11-11 09:59:30', ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([], $this->rowsOf($user));
    }

    /** The member's request goes on and their presence (#712) is still stamped; the next write records the day. */
    public function test_a_failed_daily_row_is_logged_and_recorded_by_the_next_write(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-10 22:00:00'), 'lastPlatform' => ClientPlatform::Web]);
        $repository = $this->createStub(UserDailyActivityRepository::class);
        $repository->method('record')->willThrowException(new class('Deadlock found') extends \RuntimeException implements DBALException {});
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->invokeListener($user, repository: $repository, logger: $logger);

        $this->assertSame([self::NOW, ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([], $this->rowsOf($user));

        // The next request loads the member afresh.
        $user = self::getContainer()->get(UserRepository::class)->find($user->id);
        $this->invokeListener($user, now: '2026-11-11 10:02:00');

        $this->assertSame(['2026-11-11 10:02:00', ClientPlatform::Web, null], $this->accountStateOf($user));
        $this->assertSame([['2026-11-11', 'web', null]], $this->rowsOf($user));
    }

    public function test_unauthenticated_request_is_a_no_op(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener(null);

        $this->assertSame([null, null, null], $this->accountStateOf($user));
        $this->assertSame([], $this->rowsOf($user));
    }

    public function test_subrequest_is_a_no_op(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => new DateTimeImmutable('2026-11-11 09:50:00')]);

        $this->invokeListener($user, mainRequest: false);

        $this->assertSame(['2026-11-11 09:50:00', null, null], $this->accountStateOf($user));
        $this->assertSame([], $this->rowsOf($user));
    }

    /** @param array<string, string> $server */
    private function invokeListener(
        ?User $authenticatedUser,
        bool $mainRequest = true,
        array $server = [],
        string $now = self::NOW,
        ?UserDailyActivityRepository $repository = null,
        ?LoggerInterface $logger = null,
    ): void {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($authenticatedUser);

        $em = self::getContainer()->get(EntityManagerInterface::class);

        $listener = new UserActivityListener(
            $security,
            $em,
            $repository ?? self::getContainer()->get(UserDailyActivityRepository::class),
            new MockClock($now),
            $logger ?? new NullLogger(),
        );

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/me', server: $server),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );

        $listener($event);

        // Force a clean read in the next reload.
        $em->clear();
    }

    /** @return array{?string, ?ClientPlatform, ?string} the last write's time, platform and version */
    private function accountStateOf(User $user): array
    {
        $reloaded = self::getContainer()->get(UserRepository::class)->find($user->id);

        return [$reloaded?->lastActivityDatetime?->format('Y-m-d H:i:s'), $reloaded?->lastPlatform, $reloaded?->lastAppVersion];
    }

    /** @return list<array{string, string, ?string}> the user's daily rows as day, platform and version */
    private function rowsOf(User $user): array
    {
        $rows = self::getContainer()->get(UserDailyActivityRepository::class)->findBy(['user' => $user->id], ['day' => 'ASC', 'platform' => 'ASC']);

        return array_map(static fn(UserDailyActivity $row): array => [$row->day->format('Y-m-d'), $row->platform->value, $row->appVersion], $rows);
    }
}

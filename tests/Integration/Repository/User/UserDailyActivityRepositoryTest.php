<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository\User;

use App\Entity\User;
use App\Entity\User\UserDailyActivity;
use App\Enum\User\ClientPlatform;
use App\Repository\User\UserDailyActivityRepository;
use App\Tests\Factory\User\UserDailyActivityFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class UserDailyActivityRepositoryTest extends KernelTestCase
{
    /** Two first requests of the same day both record it: the second neither fails nor adds a row. */
    public function test_recording_the_same_day_twice_keeps_one_row(): void
    {
        $user = UserFactory::createOne();

        $this->repository()->record($user, new DateTimeImmutable('2026-11-11 08:00'), ClientPlatform::Ios, '1.13.0');
        $this->repository()->record($user, new DateTimeImmutable('2026-11-11 21:30'), ClientPlatform::Ios, '1.14.0');

        $this->assertSame([['2026-11-11', 'ios', '1.14.0']], $this->rowsOf($user));
    }

    public function test_a_request_without_a_version_keeps_the_known_one(): void
    {
        $user = UserFactory::createOne();

        $this->repository()->record($user, new DateTimeImmutable('2026-11-11 08:00'), ClientPlatform::Android, '1.13.0');
        $this->repository()->record($user, new DateTimeImmutable('2026-11-11 09:00'), ClientPlatform::Android, null);

        $this->assertSame([['2026-11-11', 'android', '1.13.0']], $this->rowsOf($user));
    }

    public function test_each_platform_and_each_day_has_its_own_row(): void
    {
        $user = UserFactory::createOne();

        $this->repository()->record($user, new DateTimeImmutable('2026-11-11 08:00'), ClientPlatform::Ios, '1.13.0');
        $this->repository()->record($user, new DateTimeImmutable('2026-11-11 20:00'), ClientPlatform::Web, null);
        $this->repository()->record($user, new DateTimeImmutable('2026-11-12 08:00'), ClientPlatform::Ios, '1.13.0');

        $this->assertSame([
            ['2026-11-11', 'ios', '1.13.0'],
            ['2026-11-11', 'web', null],
            ['2026-11-12', 'ios', '1.13.0'],
        ], $this->rowsOf($user));
    }

    public function test_deleting_for_a_user_leaves_the_others(): void
    {
        $deleted = UserFactory::createOne();
        $kept = UserFactory::createOne();
        UserDailyActivityFactory::createOne(['user' => $deleted, 'day' => new DateTimeImmutable('2026-11-10')]);
        UserDailyActivityFactory::createOne(['user' => $deleted, 'day' => new DateTimeImmutable('2026-11-11'), 'platform' => ClientPlatform::Ios]);
        UserDailyActivityFactory::createOne(['user' => $kept, 'day' => new DateTimeImmutable('2026-11-11')]);

        $this->repository()->deleteForUser($deleted);

        $this->assertSame([], $this->rowsOf($deleted));
        $this->assertSame([['2026-11-11', 'web', null]], $this->rowsOf($kept));
    }

    /** The cutoff is a day: a time of day must not sweep the cutoff day itself away. */
    public function test_deleting_before_a_day_keeps_that_day(): void
    {
        $user = UserFactory::createOne();
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2025-10-10')]);
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2025-10-11')]);
        UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable('2025-10-12')]);

        $deleted = $this->repository()->deleteBefore(new DateTimeImmutable('2025-10-11 15:42'));

        $this->assertSame(1, $deleted);
        $this->assertSame([['2025-10-11', 'web', null], ['2025-10-12', 'web', null]], $this->rowsOf($user));
    }

    public function test_the_range_queries_count_distinct_members(): void
    {
        $webOnly = UserFactory::createOne(['registrationPlatform' => ClientPlatform::Web]);
        $both = UserFactory::createOne(['registrationPlatform' => ClientPlatform::Ios]);
        $outside = UserFactory::createOne(['registrationPlatform' => ClientPlatform::Android]);
        UserDailyActivityFactory::createOne(['user' => $webOnly, 'day' => new DateTimeImmutable('2026-11-01')]);
        UserDailyActivityFactory::createOne(['user' => $webOnly, 'day' => new DateTimeImmutable('2026-11-02')]);
        UserDailyActivityFactory::createOne(['user' => $both, 'day' => new DateTimeImmutable('2026-11-01'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.13.0']);
        UserDailyActivityFactory::createOne(['user' => $both, 'day' => new DateTimeImmutable('2026-11-02')]);
        // The day after the range, and the day before it.
        UserDailyActivityFactory::createOne(['user' => $outside, 'day' => new DateTimeImmutable('2026-11-03'), 'platform' => ClientPlatform::Android]);
        UserDailyActivityFactory::createOne(['user' => $outside, 'day' => new DateTimeImmutable('2026-10-31'), 'platform' => ClientPlatform::Android]);
        $from = new DateTimeImmutable('2026-11-01');
        $to = new DateTimeImmutable('2026-11-03');

        $this->assertSame([
            ['date_label' => '2026-11-01', 'segment' => 'ios', 'count' => 1],
            ['date_label' => '2026-11-01', 'segment' => 'web', 'count' => 1],
            ['date_label' => '2026-11-02', 'segment' => 'web', 'count' => 2],
        ], $this->repository()->countMembersByDayAndPlatform($from, $to));
        $this->assertSame(2, $this->repository()->countMembers($from, $to));
        $this->assertSame(['web' => 2, 'ios' => 1, 'android' => 0, 'unknown_app' => 0], $this->repository()->countMembersByPlatform($from, $to));
        $this->assertSame(1, $this->repository()->countMultiPlatformMembers($from, $to));
        $this->assertSame(['web' => 1, 'ios' => 1, 'android' => 0, 'unknown_app' => 0], $this->repository()->countMembersByRegistrationPlatform($from, $to));
    }

    /** A phone updated from a build older than the headers is one platform, not two. */
    public function test_an_unknown_app_does_not_make_a_member_multi_platform(): void
    {
        $updatedPhone = UserFactory::createOne();
        UserDailyActivityFactory::createOne(['user' => $updatedPhone, 'day' => new DateTimeImmutable('2026-11-01'), 'platform' => ClientPlatform::UnknownApp]);
        UserDailyActivityFactory::createOne(['user' => $updatedPhone, 'day' => new DateTimeImmutable('2026-11-02'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.14.0']);

        $this->assertSame(0, $this->repository()->countMultiPlatformMembers(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-03')));
    }

    public function test_app_versions_count_members_per_app_newest_first(): void
    {
        $updated = UserFactory::createOne();
        $stayed = UserFactory::createOne();
        $oldBuild = UserFactory::createOne();
        $ancient = UserFactory::createOne();
        UserDailyActivityFactory::createOne(['user' => $updated, 'day' => new DateTimeImmutable('2026-11-01'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.9.0']);
        UserDailyActivityFactory::createOne(['user' => $updated, 'day' => new DateTimeImmutable('2026-11-02'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.10.0']);
        UserDailyActivityFactory::createOne(['user' => $stayed, 'day' => new DateTimeImmutable('2026-11-02'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.9.0']);
        UserDailyActivityFactory::createOne(['user' => $stayed, 'day' => new DateTimeImmutable('2026-11-03'), 'platform' => ClientPlatform::Ios, 'appVersion' => '1.9.0']);
        UserDailyActivityFactory::createOne(['user' => $oldBuild, 'day' => new DateTimeImmutable('2026-11-02'), 'platform' => ClientPlatform::UnknownApp]);
        // The web has no version, and a version last seen before the window is not in use any more.
        UserDailyActivityFactory::createOne(['user' => $stayed, 'day' => new DateTimeImmutable('2026-11-02')]);
        UserDailyActivityFactory::createOne(['user' => $ancient, 'day' => new DateTimeImmutable('2026-10-31'), 'platform' => ClientPlatform::Android, 'appVersion' => '1.0.0']);

        $this->assertSame([
            ['platform' => 'ios', 'version' => '1.10.0', 'members' => 1],
            ['platform' => 'ios', 'version' => '1.9.0', 'members' => 2],
            ['platform' => 'unknown_app', 'version' => null, 'members' => 1],
        ], $this->repository()->countMembersByAppVersion(new DateTimeImmutable('2026-11-01')));
    }

    private function repository(): UserDailyActivityRepository
    {
        return self::getContainer()->get(UserDailyActivityRepository::class);
    }

    /** @return list<array{string, string, ?string}> the user's rows as day, platform and version, oldest first */
    private function rowsOf(User $user): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $rows = $entityManager->getRepository(UserDailyActivity::class)->findBy(['user' => $user->id], ['day' => 'ASC', 'platform' => 'ASC']);

        return array_map(static fn(UserDailyActivity $row): array => [$row->day->format('Y-m-d'), $row->platform->value, $row->appVersion], $rows);
    }
}

<?php

declare(strict_types=1);

namespace App\Repository\User;

use App\Entity\User;
use App\Entity\User\UserDailyActivity;
use App\Enum\User\ClientPlatform;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * The deletes are DQL rather than find and remove: a row has no lifecycle callback, no upload and
 * nothing cascading off it, so the ORM would only hydrate rows to throw them away.
 *
 * @extends ServiceEntityRepository<UserDailyActivity>
 */
class UserDailyActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDailyActivity::class);
    }

    /**
     * Raw SQL for its ON DUPLICATE KEY: two first requests of the day both insert, and the unique key
     * keeps one row without a failed statement. A request without a version keeps the one already known.
     */
    public function record(User $user, DateTimeImmutable $day, ClientPlatform $platform, ?string $appVersion): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO user_daily_activity (id, user_id, day, platform, app_version)
                VALUES (:id, :user, :day, :platform, :appVersion)
                ON DUPLICATE KEY UPDATE app_version = COALESCE(VALUES(app_version), app_version)
                SQL,
            [
                'id' => Uuid::v7()->toRfc4122(),
                'user' => (string) $user->id,
                'day' => $day->format('Y-m-d'),
                'platform' => $platform->value,
                'appVersion' => $appVersion,
            ],
        );
    }

    public function deleteForUser(User $user): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\UserDailyActivity a WHERE a.user = :user')
            ->setParameter('user', $user)
            ->execute();
    }

    /** @return int the number of rows deleted, every day strictly before `$day` */
    public function deleteBefore(DateTimeImmutable $day): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\UserDailyActivity a WHERE a.day < :day')
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->execute();
    }

    /**
     * Members active each day, per platform, from `$from` included to `$to` excluded.
     *
     * @return list<array{date_label: string, segment: string, count: int}>
     */
    public function countMembersByDayAndPlatform(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        /** @var list<array{day: DateTimeImmutable, platform: ClientPlatform, members: int|string}> $rows */
        $rows = $this->createRangeQueryBuilder($from, $to)
            ->select('a.day AS day', 'a.platform AS platform', 'COUNT(a.id) AS members')
            ->groupBy('a.day, a.platform')
            ->orderBy('a.day', 'ASC')
            ->addOrderBy('a.platform', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): array => [
            'date_label' => $row['day']->format('Y-m-d'),
            'segment' => $row['platform']->value,
            'count' => (int) $row['members'],
        ], $rows);
    }

    /** Distinct members active at least once between `$from` included and `$to` excluded. */
    public function countMembers(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $this->createRangeQueryBuilder($from, $to)
            ->select('COUNT(DISTINCT a.user)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> distinct members per platform over the range, every platform keyed */
    public function countMembersByPlatform(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->membersOfEveryPlatform($this->createRangeQueryBuilder($from, $to)
            ->select('a.platform AS platform', 'COUNT(DISTINCT a.user) AS members')
            ->groupBy('a.platform'));
    }

    /**
     * Members active on two platforms or more over the range: the app in the morning, the web at night.
     * An unknown app is left out, or a phone updated from a build older than the headers would count twice.
     */
    public function countMultiPlatformMembers(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return count($this->createRangeQueryBuilder($from, $to)
            ->select('IDENTITY(a.user)')
            ->andWhere('a.platform <> :unknownApp')
            ->setParameter('unknownApp', ClientPlatform::UnknownApp->value)
            ->groupBy('a.user')
            ->having('COUNT(DISTINCT a.platform) > 1')
            ->getQuery()
            ->getSingleColumnResult());
    }

    /** @return array<string, int> distinct members active over the range by the platform they registered on (#1355), every platform keyed */
    public function countMembersByRegistrationPlatform(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->membersOfEveryPlatform($this->createRangeQueryBuilder($from, $to)
            ->select('u.registrationPlatform AS platform', 'COUNT(DISTINCT u.id) AS members')
            ->join('a.user', 'u')
            ->groupBy('u.registrationPlatform'));
    }

    /**
     * Members per app and version since `$since`, the newest version first. A member who updated during
     * the window counts once under each version.
     *
     * @return list<array{platform: string, version: ?string, members: int}>
     */
    public function countMembersByAppVersion(DateTimeImmutable $since): array
    {
        /** @var list<array{platform: ClientPlatform, version: ?string, members: int|string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.platform AS platform', 'a.appVersion AS version', 'COUNT(DISTINCT a.user) AS members')
            ->where('a.day >= :since')
            ->andWhere('a.platform <> :web')
            ->setParameter('since', $since, Types::DATE_IMMUTABLE)
            ->setParameter('web', ClientPlatform::Web->value)
            ->groupBy('a.platform, a.appVersion')
            ->getQuery()
            ->getArrayResult();

        $versions = array_map(static fn(array $row): array => [
            'platform' => $row['platform']->value,
            'version' => $row['version'],
            'members' => (int) $row['members'],
        ], $rows);
        usort($versions, static fn(array $a, array $b): int => strcmp($a['platform'], $b['platform'])
            ?: version_compare($b['version'] ?? '', $a['version'] ?? ''));

        return $versions;
    }

    private function createRangeQueryBuilder(DateTimeImmutable $from, DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->where('a.day >= :from')
            ->andWhere('a.day < :to')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE);
    }

    /**
     * @param QueryBuilder $countsByPlatform selecting a `platform` and its `members`
     *
     * @return array<string, int> every platform in declaration order, zero when nobody used it
     */
    private function membersOfEveryPlatform(QueryBuilder $countsByPlatform): array
    {
        $members = array_fill_keys(array_column(ClientPlatform::cases(), 'value'), 0);
        /** @var array{platform: ClientPlatform, members: int|string} $row */
        foreach ($countsByPlatform->getQuery()->getArrayResult() as $row) {
            $members[$row['platform']->value] = (int) $row['members'];
        }

        return $members;
    }
}

<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @param string[] $userIds
     * @return User[]
     */
    public function findActiveBandSpaceMembersByIds(BandSpace $bandSpace, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->innerJoin(BandSpaceMembership::class, 'm', 'WITH', 'm.user = u')
            ->where('u.id IN (:userIds)')
            ->andWhere('m.bandSpace = :bandSpace')
            ->andWhere('m.status = :status')
            ->setParameter('userIds', $userIds)
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getResult();
    }

    public function findByTokenAndLimitDatetime(
        string $token,
        \DateTime $limitDatetime = new \DateTime('15 minutes ago')
    ): ?User
    {
        return $this->createQueryBuilder('user')
            ->where('user.token = :token')
            ->andWhere('user.resetRequestDatetime > :limit_datetime')
            ->setParameter('token', $token)
            ->setParameter('limit_datetime', $limitDatetime)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Will be deprecated in the future, the correct method is "loadUserByIdentifier"
     * @throws NonUniqueResultException
     */
    public function loadUserByUsername(string $username): ?User
    {
        return $this->findOneByEmailOrLogin($username);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmailOrLogin($identifier);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByEmailOrLogin(string $login): ?User
    {
        return $this->createQueryBuilder('user')
            ->where('user.email = :login')
            ->orWhere('user.username = :login')
            ->setParameter('login', $login)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     *
     * @return int|mixed|string
     */
    public function searchByUserName(string $username, int $limit = 15): mixed
    {
        return $this->createQueryBuilder('user')
            ->where('user.username LIKE :search')
            ->andWhere('user.deletionDatetime IS NULL')
            ->setParameter('search', $username . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findInactiveUsersSince(\DateTimeImmutable $cutoffDate, int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('user')
            ->where('user.confirmationDatetime IS NOT NULL')
            ->andWhere('user.lastLoginDatetime IS NOT NULL')
            ->andWhere('user.lastLoginDatetime < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('user.lastLoginDatetime', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find users who registered but never confirmed their email.
     *
     * @return User[]
     */
    public function findUsersWithUnconfirmedEmail(\DateTimeImmutable $registeredBefore, int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('user')
            ->where('user.confirmationDatetime IS NULL')
            ->andWhere('user.deletionDatetime IS NULL')
            ->andWhere('user.creationDatetime < :registeredBefore')
            ->setParameter('registeredBefore', $registeredBefore)
            ->orderBy('user.creationDatetime', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count users registered within a date range.
     */
    public function countRegistrationsSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.creationDatetime >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unique logins within a date range.
     */
    public function countLoginsSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.lastLoginDatetime >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total confirmed users.
     */
    public function countTotalUsers(): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.confirmationDatetime IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unconfirmed accounts.
     */
    public function countUnconfirmedAccounts(): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.confirmationDatetime IS NULL')
            ->andWhere('user.token IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calculate retention rate for users registered N days ago.
     * Returns the percentage of users who logged in after their registration day.
     */
    public function calculateRetentionRate(int $daysAgo): ?float
    {
        $registrationStart = new \DateTimeImmutable("-{$daysAgo} days midnight");
        $registrationEnd = new \DateTimeImmutable("-" . ($daysAgo - 1) . " days midnight");

        // Count users registered on that day
        $totalRegistered = (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.creationDatetime >= :start')
            ->andWhere('user.creationDatetime < :end')
            ->andWhere('user.confirmationDatetime IS NOT NULL')
            ->setParameter('start', $registrationStart)
            ->setParameter('end', $registrationEnd)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalRegistered === 0) {
            return null;
        }

        // Count users who logged in after their registration day
        $retained = (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->where('user.creationDatetime >= :start')
            ->andWhere('user.creationDatetime < :end')
            ->andWhere('user.confirmationDatetime IS NOT NULL')
            ->andWhere('user.lastLoginDatetime > user.creationDatetime')
            ->setParameter('start', $registrationStart)
            ->setParameter('end', $registrationEnd)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($retained / $totalRegistered) * 100, 1);
    }

    /**
     * Find registrations with profile completion info within a date range.
     *
     * @return array<int, array{user: User, profile_completion: int}>
     */
    public function findRecentRegistrationsWithCompletion(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        $users = $this->createQueryBuilder('user')
            ->select('user, profile, profilePicture')
            ->join('user.profile', 'profile')
            ->leftJoin('user.profilePicture', 'profilePicture')
            ->where('user.confirmationDatetime IS NOT NULL')
            ->andWhere('user.creationDatetime >= :from')
            ->andWhere('user.creationDatetime < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('user.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'user' => $user,
                'profile_completion' => $this->calculateProfileCompletion($user),
            ];
        }

        return $result;
    }

    /**
     * Find users registered within a date range with empty profiles (no avatar AND empty bio).
     *
     * @return array<int, array{user: User, profile_completion: int}>
     */
    public function findRecentEmptyProfiles(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        $users = $this->createQueryBuilder('user')
            ->select('user, profile')
            ->join('user.profile', 'profile')
            ->where('user.creationDatetime >= :from')
            ->andWhere('user.creationDatetime < :to')
            ->andWhere('user.confirmationDatetime IS NOT NULL')
            ->andWhere('user.profilePicture IS NULL')
            ->andWhere('profile.bio IS NULL OR profile.bio = :emptyBio')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('emptyBio', '')
            ->orderBy('user.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'user' => $user,
                'profile_completion' => $this->calculateProfileCompletion($user),
            ];
        }

        return $result;
    }

    /**
     * Calculate profile completion percentage for a user.
     */
    private function calculateProfileCompletion(User $user): int
    {
        $score = 0;
        $maxScore = 5;

        // Has profile picture (20%)
        if ($user->profilePicture instanceof \App\Entity\Image\UserProfilePicture) {
            $score++;
        }

        $profile = $user->profile;

        // Has bio (20%)
        if ($profile->bio !== null && $profile->bio !== '') {
            $score++;
        }

        // Has display name (20%)
        if ($profile->displayName !== null && $profile->displayName !== '') {
            $score++;
        }

        // Has location (20%)
        if ($profile->location !== null && $profile->location !== '') {
            $score++;
        }

        // Has cover picture (20%)
        if ($profile->coverPicture instanceof \App\Entity\Image\UserProfileCoverPicture) {
            $score++;
        }

        return (int) round(($score / $maxScore) * 100);
    }

    /**
     * Count registrations grouped by date within a range.
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countRegistrationsByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM fos_user
             WHERE creation_datetime >= :from AND creation_datetime < :to
             GROUP BY DATE(creation_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        return array_map(
            fn (array $row): array => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    /**
     * Count logins grouped by date within a range.
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countLoginsByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(last_login_datetime) AS date_label, COUNT(id) AS count
             FROM fos_user
             WHERE last_login_datetime >= :from AND last_login_datetime < :to
             GROUP BY DATE(last_login_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        return array_map(
            fn (array $row): array => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    /**
     * Get profile completion statistics for users registered within a date range.
     *
     * @return array{avg_percent: float, total: int, levels: array{empty: int, basic: int, complete: int}}
     */
    public function getProfileCompletionStats(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $users = $this->createQueryBuilder('user')
            ->select('user, profile, profilePicture, coverPicture')
            ->join('user.profile', 'profile')
            ->leftJoin('user.profilePicture', 'profilePicture')
            ->leftJoin('profile.coverPicture', 'coverPicture')
            ->where('user.creationDatetime >= :from')
            ->andWhere('user.creationDatetime < :to')
            ->andWhere('user.confirmationDatetime IS NOT NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $total = count($users);
        if ($total === 0) {
            return ['avg_percent' => 0, 'total' => 0, 'levels' => ['empty' => 0, 'basic' => 0, 'complete' => 0]];
        }

        $totalPercent = 0;
        $levels = ['empty' => 0, 'basic' => 0, 'complete' => 0];

        foreach ($users as $user) {
            $completion = $this->calculateProfileCompletion($user);
            $totalPercent += $completion;

            if ($completion <= 20) {
                $levels['empty']++;
            } elseif ($completion < 80) {
                $levels['basic']++;
            } else {
                $levels['complete']++;
            }
        }

        return [
            'avg_percent' => round($totalPercent / $total, 1),
            'total' => $total,
            'levels' => $levels,
        ];
    }
}

<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
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
            ->andWhere('user.token IS NOT NULL')
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
     * Find recent registrations with profile completion info.
     *
     * @return array<int, array{user: User, profile_completion: int}>
     */
    public function findRecentRegistrationsWithCompletion(int $limit = 5): array
    {
        $users = $this->createQueryBuilder('user')
            ->select('user, profile, profilePicture')
            ->join('user.profile', 'profile')
            ->leftJoin('user.profilePicture', 'profilePicture')
            ->where('user.confirmationDatetime IS NOT NULL')
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
     * Find users registered recently with empty profiles (no avatar AND empty bio).
     *
     * @return array<int, array{user: User, profile_completion: int}>
     */
    public function findRecentEmptyProfiles(\DateTimeImmutable $since, int $limit = 10): array
    {
        $users = $this->createQueryBuilder('user')
            ->select('user, profile')
            ->join('user.profile', 'profile')
            ->where('user.creationDatetime >= :since')
            ->andWhere('user.confirmationDatetime IS NOT NULL')
            ->andWhere('user.profilePicture IS NULL')
            ->andWhere('profile.bio IS NULL OR profile.bio = :emptyBio')
            ->setParameter('since', $since)
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
    public function calculateProfileCompletion(User $user): int
    {
        $score = 0;
        $maxScore = 5;

        // Has profile picture (20%)
        if ($user->getProfilePicture() !== null) {
            $score++;
        }

        $profile = $user->getProfile();

        // Has bio (20%)
        if ($profile->getBio() !== null && $profile->getBio() !== '') {
            $score++;
        }

        // Has display name (20%)
        if ($profile->getDisplayName() !== null && $profile->getDisplayName() !== '') {
            $score++;
        }

        // Has location (20%)
        if ($profile->getLocation() !== null && $profile->getLocation() !== '') {
            $score++;
        }

        // Has cover picture (20%)
        if ($profile->getCoverPicture() !== null) {
            $score++;
        }

        return (int) round(($score / $maxScore) * 100);
    }

    /**
     * Get profile completion statistics for users registered within a period.
     *
     * @return array{avg_percent: float, total: int, levels: array{empty: int, basic: int, complete: int}}
     */
    public function getProfileCompletionStats(\DateTimeImmutable $since): array
    {
        $users = $this->createQueryBuilder('user')
            ->select('user, profile, profilePicture, coverPicture')
            ->join('user.profile', 'profile')
            ->leftJoin('user.profilePicture', 'profilePicture')
            ->leftJoin('profile.coverPicture', 'coverPicture')
            ->where('user.creationDatetime >= :since')
            ->andWhere('user.confirmationDatetime IS NOT NULL')
            ->setParameter('since', $since)
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

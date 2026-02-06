<?php

declare(strict_types=1);

namespace App\Repository\User;

use App\Entity\User;
use App\Entity\User\UserEmailLog;
use App\Enum\User\UserEmailType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEmailLog>
 */
class UserEmailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEmailLog::class);
    }

    public function findOneByUserAndType(User $user, UserEmailType $emailType, ?string $referenceId = null): ?UserEmailLog
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.emailType = :emailType')
            ->setParameter('user', $user)
            ->setParameter('emailType', $emailType)
            ->orderBy('l.sentDatetime', 'DESC')
            ->setMaxResults(1);

        if ($referenceId !== null) {
            $qb->andWhere('l.referenceId = :referenceId')
                ->setParameter('referenceId', $referenceId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneByUserAndTypeSince(
        User $user,
        UserEmailType $emailType,
        DateTimeImmutable $since,
        ?string $referenceId = null
    ): ?UserEmailLog {
        $qb = $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.emailType = :emailType')
            ->andWhere('l.sentDatetime >= :since')
            ->setParameter('user', $user)
            ->setParameter('emailType', $emailType)
            ->setParameter('since', $since)
            ->orderBy('l.sentDatetime', 'DESC')
            ->setMaxResults(1);

        if ($referenceId !== null) {
            $qb->andWhere('l.referenceId = :referenceId')
                ->setParameter('referenceId', $referenceId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countByUserAndType(User $user, UserEmailType $emailType): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.user = :user')
            ->andWhere('l.emailType = :emailType')
            ->setParameter('user', $user)
            ->setParameter('emailType', $emailType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByTypeBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('l.emailType, COUNT(l.id) as count')
            ->where('l.sentDatetime >= :from')
            ->andWhere('l.sentDatetime < :to')
            ->groupBy('l.emailType')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['emailType']->value] = (int) $row['count'];
        }

        return $counts;
    }
}

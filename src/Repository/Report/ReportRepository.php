<?php declare(strict_types=1);

namespace App\Repository\Report;

use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    public function findPendingByReporterAndTarget(User $reporter, ReportTargetType $targetType, string $targetId): ?Report
    {
        return $this->createQueryBuilder('r')
            ->where('r.reporter = :reporter')
            ->andWhere('r.targetType = :targetType')
            ->andWhere('r.targetId = :targetId')
            ->andWhere('r.resolutionDatetime IS NULL')
            ->setParameter('reporter', $reporter)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

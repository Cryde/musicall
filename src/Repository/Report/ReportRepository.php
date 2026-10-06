<?php declare(strict_types=1);

namespace App\Repository\Report;

use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Report\ReportOutcome;
use App\Enum\Report\ReportTargetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /** Null for an id that is not a uuid, rather than a conversion error from the column type. */
    public function findOneById(string $id): ?Report
    {
        return Uuid::isValid($id) ? $this->find($id) : null;
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

    /**
     * The moderation queue: pending first, newest first within each group.
     *
     * @return Paginator<Report>
     */
    public function findForAdmin(?bool $pending, int $offset, int $limit): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'reporter')->addSelect('reporter')
            ->leftJoin('r.targetAuthor', 'author')->addSelect('author')
            ->leftJoin('r.resolvedBy', 'moderator')->addSelect('moderator')
            ->addSelect('CASE WHEN r.resolutionDatetime IS NULL THEN 0 ELSE 1 END AS HIDDEN pendingFirst')
            ->orderBy('pendingFirst', 'ASC')
            ->addOrderBy('r.creationDatetime', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($pending !== null) {
            $queryBuilder->andWhere($pending ? 'r.resolutionDatetime IS NULL' : 'r.resolutionDatetime IS NOT NULL');
        }

        return new Paginator($queryBuilder->getQuery(), fetchJoinCollection: false);
    }

    /**
     * How many pending reports each of these targets has, keyed "type|id", in one query.
     *
     * @param list<Report> $reports
     *
     * @return array<string, int>
     */
    public function countPendingPerTarget(array $reports): array
    {
        if ($reports === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('r.targetType AS targetType', 'r.targetId AS targetId', 'COUNT(r.id) AS total')
            ->where('r.resolutionDatetime IS NULL')
            ->andWhere('r.targetId IN (:targetIds)')
            ->setParameter('targetIds', array_values(array_unique(array_map(static fn (Report $report): string => $report->targetId, $reports))))
            ->groupBy('r.targetType', 'r.targetId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['targetType']->value . '|' . $row['targetId']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countPending(): int
    {
        return $this->count(['resolutionDatetime' => null]);
    }

    /**
     * Closes every pending report on one target at once, since a moderator decides about the content,
     * not about one report of it. DQL rather than loading each: a report has no lifecycle listener and
     * only these columns change. Reports already in memory are not refreshed by it.
     */
    public function resolvePendingForTarget(ReportTargetType $targetType, string $targetId, User $moderator, ReportOutcome $outcome): void
    {
        $this->getEntityManager()
            ->createQuery(<<<'DQL'
                UPDATE App\Entity\Report\Report r
                SET r.resolutionDatetime = :now, r.resolvedBy = :moderator, r.outcome = :outcome
                WHERE r.targetType = :targetType AND r.targetId = :targetId AND r.resolutionDatetime IS NULL
                DQL)
            ->setParameter('now', new \DateTimeImmutable(), 'datetime_immutable')
            ->setParameter('moderator', $moderator)
            ->setParameter('outcome', $outcome->value)
            ->setParameter('targetType', $targetType->value)
            ->setParameter('targetId', $targetId)
            ->execute();
    }
}

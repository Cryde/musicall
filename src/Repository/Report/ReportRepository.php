<?php declare(strict_types=1);

namespace App\Repository\Report;

use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Report\ReportOutcome;
use App\Enum\Report\ReportTargetType;
use Doctrine\DBAL\LockMode;
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
     * Every pending report on one target, with its reporter, since a moderator decides about the
     * content, not about one report of it.
     *
     * @return list<Report>
     */
    public function findPendingForTarget(ReportTargetType $targetType, string $targetId): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('reporter')
            ->join('r.reporter', 'reporter')
            ->where('r.targetType = :targetType AND r.targetId = :targetId AND r.resolutionDatetime IS NULL')
            ->setParameter('targetType', $targetType->value)
            ->setParameter('targetId', $targetId)
            ->orderBy('r.creationDatetime', 'ASC')
            ->getQuery()
            // Locked until the decision commits, so a second moderator deciding at the same moment
            // waits, then finds nothing pending and tells nobody a second time. Needs a transaction.
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * Closes exactly these reports, so the ones whose reporters are told are the ones closed: a report
     * filed while the moderator was deciding stays pending. DQL rather than flushing each: a report has
     * no lifecycle listener and only these columns change. The entities in memory are not refreshed.
     *
     * @param list<Report> $reports
     */
    public function resolve(array $reports, User $moderator, ReportOutcome $outcome): void
    {
        if ($reports === []) {
            return;
        }

        $this->getEntityManager()
            ->createQuery(<<<'DQL'
                UPDATE App\Entity\Report\Report r
                SET r.resolutionDatetime = :now, r.resolvedBy = :moderator, r.outcome = :outcome
                WHERE r.id IN (:ids) AND r.resolutionDatetime IS NULL
                DQL)
            ->setParameter('now', new \DateTimeImmutable(), 'datetime_immutable')
            ->setParameter('moderator', $moderator)
            ->setParameter('outcome', $outcome->value)
            ->setParameter('ids', array_map(static fn (Report $report): string => (string) $report->id, $reports))
            ->execute();
    }
}

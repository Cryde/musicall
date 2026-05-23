<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\Filter\TaskFilter;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findByBandSpace(BandSpace $bandSpace, TaskFilter $filter): array
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('u', 'c', 'a')
            ->leftJoin('t.createdBy', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.assignees', 'a')
            ->where('t.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.creationDatetime', 'DESC');

        if ($filter->archived === true) {
            $qb->andWhere('t.archiveDatetime IS NOT NULL');
        } else {
            $qb->andWhere('t.archiveDatetime IS NULL');
        }

        if ($filter->status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $filter->status);
        }

        if ($filter->categoryId !== null) {
            $qb->andWhere('t.category = :categoryId')
                ->setParameter('categoryId', $filter->categoryId);
        }

        if ($filter->assigneeId !== null) {
            $qb->andWhere('a.id = :assigneeId')
                ->setParameter('assigneeId', $filter->assigneeId);
        }

        if ($filter->priority !== null) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $filter->priority);
        }

        $trimmedQuery = $filter->query !== null ? trim($filter->query) : '';
        if ($trimmedQuery !== '') {
            $qb->andWhere('LOWER(t.title) LIKE :query OR LOWER(t.description) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($trimmedQuery) . '%');
        }

        if ($filter->dueDateFrom instanceof \DateTimeImmutable) {
            $qb->andWhere('t.dueDate >= :dueDateFrom')
                ->setParameter('dueDateFrom', $filter->dueDateFrom);
        }

        if ($filter->dueDateTo instanceof \DateTimeImmutable) {
            $qb->andWhere('t.dueDate <= :dueDateTo')
                ->setParameter('dueDateTo', $filter->dueDateTo);
        }

        if ($filter->overdueOnly) {
            $qb->andWhere('t.dueDate < :today')
                ->andWhere('t.status != :doneStatus')
                ->setParameter('today', new DateTimeImmutable('today'))
                ->setParameter('doneStatus', TaskStatus::Done->value);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int, array{id: string, position: int}> $positions
     */
    public function bulkUpdatePositions(array $positions): void
    {
        if (count($positions) === 0) {
            return;
        }

        $caseParts = [];
        $params = [];
        foreach ($positions as $index => $item) {
            $caseParts[] = sprintf('WHEN :id%d THEN :pos%d', $index, $index);
            $params['id' . $index] = $item['id'];
            $params['pos' . $index] = $item['position'];
        }

        $params['ids'] = array_column($positions, 'id');

        $sql = sprintf(
            'UPDATE task SET position = CASE id %s END WHERE id IN (:ids)',
            implode(' ', $caseParts)
        );

        $this->getEntityManager()->getConnection()->executeStatement(
            $sql,
            $params,
            ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }

    /**
     * @param string[] $ids
     * @return Task[]
     */
    public function findByIdsAndBandSpace(array $ids, BandSpace $bandSpace): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->andWhere('t.bandSpace = :bandSpace')
            ->setParameter('ids', $ids)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?Task
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u', 'c', 'a')
            ->leftJoin('t.createdBy', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.assignees', 'a')
            ->where('t.id = :id')
            ->andWhere('t.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Task[]
     */
    /**
     * Per-status counts of non-archived tasks, plus an "overdue" count
     * (status != done AND dueDate < now). Returned shape:
     *   ['todo' => int, 'in_progress' => int, 'done' => int, 'overdue' => int]
     *
     * @return array{todo: int, in_progress: int, done: int, overdue: int}
     */
    public function getStatusCounts(BandSpace $bandSpace, DateTimeImmutable $now): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) AS row_count')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('t.archiveDatetime IS NULL')
            ->groupBy('t.status')
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getArrayResult();

        $counts = [
            TaskStatus::Todo->value => 0,
            TaskStatus::InProgress->value => 0,
            TaskStatus::Done->value => 0,
        ];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof TaskStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['row_count'];
        }

        $overdue = (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('t.archiveDatetime IS NULL')
            ->andWhere('t.status != :doneStatus')
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.dueDate < :now')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('doneStatus', TaskStatus::Done)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'todo' => $counts[TaskStatus::Todo->value],
            'in_progress' => $counts[TaskStatus::InProgress->value],
            'done' => $counts[TaskStatus::Done->value],
            'overdue' => $overdue,
        ];
    }

    /**
     * @return Task[]
     */
    public function findUpcomingForBand(BandSpace $bandSpace, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('c')
            ->leftJoin('t.category', 'c')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('t.dueDate IS NOT NULL')
            ->andWhere('t.dueDate >= :from')
            ->andWhere('t.dueDate <= :to')
            ->andWhere('t.archiveDatetime IS NULL')
            ->andWhere('t.status != :doneStatus')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('doneStatus', TaskStatus::Done->value)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\User;
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
     * The tasks a kanban column currently holds: same band space, same status, not archived.
     * Reorder and move payloads are checked against it, see TaskColumnPositionsGuard.
     *
     * @return Task[]
     */
    public function findActiveColumn(BandSpace $bandSpace, TaskStatus $status): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('t.status = :status')
            ->andWhere('t.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }

    /**
     * Where a task joining a column goes when the client sent no ordering, which happens while a
     * server-side task filter hides part of that column from it: the end, so the task cannot take
     * a number one of the hidden tasks already holds.
     */
    public function findNextPositionInColumn(BandSpace $bandSpace, TaskStatus $status): int
    {
        $highestPosition = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('t.status = :status')
            ->andWhere('t.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $highestPosition === null ? 0 : (int) $highestPosition + 1;
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
     * The tasks come back in the order the ids were given. A bulk write applies them one by one and
     * can refuse the whole batch over any of them, so the caller's own order is what makes a batch
     * behave the same way twice and lets an answer name the tasks in the way they were selected.
     *
     * @param string[] $ids
     * @return Task[]
     */
    public function findByIdsAndBandSpace(array $ids, BandSpace $bandSpace): array
    {
        if (count($ids) === 0) {
            return [];
        }

        $tasks = $this->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->andWhere('t.bandSpace = :bandSpace')
            ->setParameter('ids', $ids)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getResult();

        $rank = array_flip(array_values($ids));
        usort($tasks, static fn(Task $a, Task $b): int
            => ($rank[(string) $a->id] ?? PHP_INT_MAX) <=> ($rank[(string) $b->id] ?? PHP_INT_MAX));

        return $tasks;
    }

    /**
     * Bulk lookup by id (any band space) - used to refresh the live task title for the
     * notification feed in one query.
     *
     * @param string[] $ids
     * @return Task[]
     */
    public function findByIds(array $ids): array
    {
        if (count($ids) === 0) {
            return [];
        }

        // The band space comes along because the enricher reads it on every row to work out whether
        // the reader may still be shown a live title, and one proxy load per task would undo the
        // point of fetching them in a single query.
        return $this->createQueryBuilder('t')
            ->innerJoin('t.bandSpace', 'bs')
            ->addSelect('bs')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Every task of a band space one user is currently assigned to, archived and done ones included:
     * an assignment says who is on the hook today, so a member on their way out comes off all of them.
     *
     * The join filters, it deliberately does not `addSelect('a')`: hydrating the assignee collection
     * through a join that only matches one member would leave each task holding a partial collection,
     * and flushing that would drop the co-assignees the query never selected.
     *
     * @return Task[]
     */
    public function findByBandSpaceAndAssignee(BandSpace $bandSpace, User $assignee): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.assignees', 'a')
            ->where('t.bandSpace = :bandSpace')
            ->andWhere('a.id = :assigneeId')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('assigneeId', (string) $assignee->id)
            ->orderBy('t.creationDatetime', 'ASC')
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

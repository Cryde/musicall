<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\Task;
use App\Entity\BandSpace\TaskComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskComment>
 */
class TaskCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskComment::class);
    }

    /**
     * @return TaskComment[]
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('tc')
            ->addSelect('u')
            ->leftJoin('tc.author', 'u')
            ->where('tc.task = :task')
            ->setParameter('task', $task)
            ->orderBy('tc.creationDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndTask(string $id, Task $task): ?TaskComment
    {
        return $this->createQueryBuilder('tc')
            ->addSelect('u')
            ->leftJoin('tc.author', 'u')
            ->where('tc.id = :id')
            ->andWhere('tc.task = :task')
            ->setParameter('id', $id)
            ->setParameter('task', $task)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $taskIds
     * @return array<string, int>
     */
    public function countByTaskIds(array $taskIds): array
    {
        if (count($taskIds) === 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('tc')
            ->select('IDENTITY(tc.task) AS task_id, COUNT(tc.id) AS cnt')
            ->where('tc.task IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->groupBy('tc.task')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['task_id']] = (int) $row['cnt'];
        }

        return $counts;
    }
}

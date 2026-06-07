<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\Task;
use App\Entity\BandSpace\TaskComment;
use App\Entity\User;
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

    /**
     * Distinct authors of every comment on a task, as User entities (single query).
     *
     * ORM 3 forbids selecting a joined alias alone, so we select the root + eager-load the
     * author, then de-duplicate by id in PHP (cf. CommentRepository::findThreadAuthors).
     *
     * @return User[]
     */
    public function findCommentAuthorsByTask(Task $task): array
    {
        /** @var TaskComment[] $comments */
        $comments = $this->createQueryBuilder('tc')
            ->innerJoin('tc.author', 'author')
            ->addSelect('author')
            ->where('tc.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getResult();

        $authorsById = [];
        foreach ($comments as $comment) {
            $authorsById[(string) $comment->author->id] = $comment->author;
        }

        return array_values($authorsById);
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

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
}

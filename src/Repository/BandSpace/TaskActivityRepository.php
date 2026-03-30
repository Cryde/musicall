<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\Task;
use App\Entity\BandSpace\TaskActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskActivity>
 */
class TaskActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskActivity::class);
    }

    /**
     * @return TaskActivity[]
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('ta')
            ->addSelect('u')
            ->leftJoin('ta.actor', 'u')
            ->where('ta.task = :task')
            ->setParameter('task', $task)
            ->orderBy('ta.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

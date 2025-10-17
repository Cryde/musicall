<?php declare(strict_types=1);

namespace App\Repository\Comment;

use App\Entity\Comment\CommentThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentThread>
 */
class CommentThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentThread::class);
    }
}

<?php

namespace App\Repository\Comment;

use App\Entity\Comment\CommentThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CommentThread|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommentThread|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommentThread[]    findAll()
 * @method CommentThread[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentThread::class);
    }
}

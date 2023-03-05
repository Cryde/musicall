<?php

namespace App\Repository\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ForumPost|null find($id, $lockMode = null, $lockVersion = null)
 * @method ForumPost|null findOneBy(array $criteria, array $orderBy = null)
 * @method ForumPost[]    findAll()
 * @method ForumPost[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }
}

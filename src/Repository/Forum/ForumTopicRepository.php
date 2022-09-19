<?php

namespace App\Repository\Forum;

use App\Entity\Forum\ForumTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ForumTopic|null find($id, $lockMode = null, $lockVersion = null)
 * @method ForumTopic|null findOneBy(array $criteria, array $orderBy = null)
 * @method ForumTopic[]    findAll()
 * @method ForumTopic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForumTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumTopic::class);
    }
}

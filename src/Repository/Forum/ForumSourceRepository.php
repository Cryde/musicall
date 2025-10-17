<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ForumSource|null find($id, $lockMode = null, $lockVersion = null)
 * @method ForumSource|null findOneBy(array $criteria, array $orderBy = null)
 * @method ForumSource[]    findAll()
 * @method ForumSource[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForumSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumSource::class);
    }
}

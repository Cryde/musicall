<?php declare(strict_types=1);

namespace App\Repository\Metric;

use App\Entity\Metric\ViewCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ViewCache|null find($id, $lockMode = null, $lockVersion = null)
 * @method ViewCache|null findOneBy(array $criteria, array $orderBy = null)
 * @method ViewCache[]    findAll()
 * @method ViewCache[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ViewCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ViewCache::class);
    }
}

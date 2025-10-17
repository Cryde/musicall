<?php declare(strict_types=1);

namespace App\Repository\Metric;

use App\Entity\Metric\ViewCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ViewCache>
 */
class ViewCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ViewCache::class);
    }
}

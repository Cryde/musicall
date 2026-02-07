<?php declare(strict_types=1);

namespace App\Repository\Metric;

use App\Entity\Metric\VoteCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoteCache>
 */
class VoteCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoteCache::class);
    }
}

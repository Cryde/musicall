<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumSource>
 */
class ForumSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumSource::class);
    }
}

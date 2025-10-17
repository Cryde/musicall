<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumCategory>
 */
class ForumCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumCategory::class);
    }
}

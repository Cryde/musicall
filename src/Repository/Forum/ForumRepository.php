<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    public function findBySlug(string $slug): ?Forum
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}

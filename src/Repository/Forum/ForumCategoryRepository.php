<?php

declare(strict_types=1);

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

    /**
     * @return ForumCategory[]
     */
    public function findBySourceSlugAndLocaleOrderedByPosition(string $sourceSlug, string $locale): array
    {
        return $this->createQueryBuilder('fc')
            ->innerJoin('fc.forumSource', 'fs')
            ->innerJoin('fc.forums', 'f')
            ->addSelect('f')
            ->where('fs.slug = :slug')
            ->andWhere('fc.locale = :locale')
            ->setParameter('slug', $sourceSlug)
            ->setParameter('locale', $locale)
            ->orderBy('fc.position', 'ASC')
            ->addOrderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

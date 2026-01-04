<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PublicationSubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationSubCategory>
 */
class PublicationSubCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationSubCategory::class);
    }

    /**
     * @return PublicationSubCategory[]
     */
    public function findByTypeOrderedByPosition(int $type): array
    {
        return $this->createQueryBuilder('psc')
            ->where('psc.type = :type')
            ->setParameter('type', $type)
            ->orderBy('psc.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

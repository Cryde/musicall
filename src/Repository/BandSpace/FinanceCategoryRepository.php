<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\FinanceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinanceCategory>
 */
class FinanceCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceCategory::class);
    }

    /**
     * @return FinanceCategory[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?FinanceCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextPosition(BandSpace $bandSpace, ?FinanceCategory $parent): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace);

        if ($parent instanceof \App\Entity\BandSpace\FinanceCategory) {
            $qb->andWhere('c.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('c.parent IS NULL');
        }

        $maxPosition = $qb->getQuery()->getSingleScalarResult();

        return $maxPosition !== null ? ((int) $maxPosition) + 1 : 0;
    }

    public function existsByBandSpace(BandSpace $bandSpace): bool
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}

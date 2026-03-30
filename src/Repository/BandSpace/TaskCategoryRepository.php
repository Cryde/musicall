<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TaskCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskCategory>
 */
class TaskCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskCategory::class);
    }

    /**
     * @return TaskCategory[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?TaskCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByBandSpace(BandSpace $bandSpace): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return string[]
     */
    public function findColorsByBandSpace(BandSpace $bandSpace): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.color')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $row): string => $row['color'], $rows);
    }
}

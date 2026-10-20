<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechRiderItem>
 */
class TechRiderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechRiderItem::class);
    }

    /**
     * Scoped by rider so an item id from another rider resolves to null rather than
     * granting access.
     */
    public function findOneByIdAndRider(string $id, TechRider $techRider): ?TechRiderItem
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.techRider = :techRider')
            ->setParameter('id', $id)
            ->setParameter('techRider', $techRider)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TechRiderItem[]
     */
    public function findByRider(TechRider $techRider): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.techRider = :techRider')
            ->setParameter('techRider', $techRider)
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function nextPosition(TechRider $techRider): int
    {
        $max = $this->createQueryBuilder('s')
            ->select('MAX(s.position)')
            ->where('s.techRider = :techRider')
            ->setParameter('techRider', $techRider)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? 0 : ((int) $max) + 1;
    }

    /**
     * @param list<string> $riderIds
     * @return array<string, int> item count keyed by rider id
     */
    public function countByRiders(array $riderIds): array
    {
        if ($riderIds === []) {
            return [];
        }

        /** @var array<int, array{riderId: string, total: int}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.techRider) AS riderId, COUNT(s.id) AS total')
            ->where('s.techRider IN (:riderIds)')
            ->setParameter('riderIds', $riderIds)
            ->groupBy('s.techRider')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['riderId']] = (int) $row['total'];
        }

        return $counts;
    }
}

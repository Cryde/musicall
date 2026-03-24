<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceCategory;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\RecurrenceInterval;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinanceRecurrence>
 */
class FinanceRecurrenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceRecurrence::class);
    }

    /**
     * @return FinanceRecurrence[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.category', 'c')->addSelect('c')
            ->join('c.bandSpace', 'bs')->addSelect('bs')
            ->where('c.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('r.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasOverlap(FinanceCategory $category, RecurrenceInterval $interval, \DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.category = :category')
            ->andWhere('r.interval = :interval')
            ->andWhere('r.isActive = true')
            ->andWhere('r.startDate < :endDate')
            ->andWhere('r.endDate > :startDate')
            ->setParameter('category', $category)
            ->setParameter('interval', $interval)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?FinanceRecurrence
    {
        return $this->createQueryBuilder('r')
            ->join('r.category', 'c')->addSelect('c')
            ->join('c.bandSpace', 'bs')->addSelect('bs')
            ->where('r.id = :id')
            ->andWhere('c.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return FinanceRecurrence[]
     */
    public function findActivePersonalByMember(BandSpaceMembership $membership): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.category', 'c')
            ->join('r.entries', 'e')
            ->where('c.bandSpace = :bandSpace')
            ->andWhere('r.scope = :scope')
            ->andWhere('r.isActive = true')
            ->andWhere('e.member = :member')
            ->setParameter('bandSpace', $membership->bandSpace)
            ->setParameter('scope', FinanceEntryScope::Personal)
            ->setParameter('member', $membership)
            ->groupBy('r.id')
            ->getQuery()
            ->getResult();
    }
}

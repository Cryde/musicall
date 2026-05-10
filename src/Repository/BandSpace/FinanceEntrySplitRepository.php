<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceEntrySplit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinanceEntrySplit>
 */
class FinanceEntrySplitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceEntrySplit::class);
    }

    /**
     * @return FinanceEntrySplit[]
     */
    public function findByEntry(FinanceEntry $entry): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.entry', 'e')->addSelect('e')
            ->join('e.category', 'c')->addSelect('c')
            ->join('c.bandSpace', 'bs')->addSelect('bs')
            ->leftJoin('s.member', 'm')->addSelect('m')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->where('s.entry = :entry')
            ->setParameter('entry', $entry)
            ->orderBy('s.creationDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndEntry(string $id, FinanceEntry $entry): ?FinanceEntrySplit
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.entry = :entry')
            ->setParameter('id', $id)
            ->setParameter('entry', $entry)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByEntryAndMember(FinanceEntry $entry, BandSpaceMembership $member): ?FinanceEntrySplit
    {
        return $this->createQueryBuilder('s')
            ->where('s.entry = :entry')
            ->andWhere('s.member = :member')
            ->setParameter('entry', $entry)
            ->setParameter('member', $member)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param FinanceEntry[] $entries
     * @return array<string, int> keyed by entry ID
     */
    public function getSumsByEntries(array $entries): array
    {
        if (\count($entries) === 0) {
            return [];
        }

        $entryIds = array_map(fn (FinanceEntry $e): string => (string) $e->id, $entries);

        $results = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.entry) AS entry_id, COALESCE(SUM(s.amount), 0) AS total')
            ->where('s.entry IN (:entries)')
            ->setParameter('entries', $entryIds)
            ->groupBy('s.entry')
            ->getQuery()
            ->getArrayResult();

        $sums = [];
        foreach ($results as $row) {
            $sums[$row['entry_id']] = (int) $row['total'];
        }

        return $sums;
    }

    public function getSumByEntry(FinanceEntry $entry): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.amount), 0)')
            ->where('s.entry = :entry')
            ->setParameter('entry', $entry)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}

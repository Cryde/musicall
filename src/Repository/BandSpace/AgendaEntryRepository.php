<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgendaEntry>
 */
class AgendaEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgendaEntry::class);
    }

    /**
     * @return AgendaEntry[]
     */
    public function findUpcomingForBand(BandSpace $bandSpace, DateTimeInterface $from, DateTimeInterface $to): array
    {
        // Overlap semantics: an entry intersects [from, to] iff it starts no later than `to`
        // and its effective end (endDatetime, falling back to eventDatetime for point events)
        // is no earlier than `from`.
        return $this->createQueryBuilder('a')
            ->addSelect('c')
            ->leftJoin('a.creator', 'c')
            ->where('a.bandSpace = :bandSpace')
            ->andWhere('a.eventDatetime <= :to')
            ->andWhere('COALESCE(a.endDatetime, a.eventDatetime) >= :from')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.eventDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AgendaEntry[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('c')
            ->leftJoin('a.creator', 'c')
            ->where('a.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('a.eventDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?AgendaEntry
    {
        return $this->createQueryBuilder('a')
            ->addSelect('c')
            ->leftJoin('a.creator', 'c')
            ->where('a.id = :id')
            ->andWhere('a.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

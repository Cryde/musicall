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
        return $this->createQueryBuilder('a')
            ->addSelect('c')
            ->leftJoin('a.creator', 'c')
            ->where('a.bandSpace = :bandSpace')
            ->andWhere('a.eventDatetime >= :from')
            ->andWhere('a.eventDatetime <= :to')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.eventDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

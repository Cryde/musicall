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
        // Two overlap semantics in one query:
        //  - One-off entries (recurrenceFrequency IS NULL): the entry intersects [from, to] when it
        //    starts no later than `to` and its effective end (endDatetime, fallback eventDatetime) is
        //    no earlier than `from`.
        //  - Recurring entries: the *rule* overlaps [from, to] when the first occurrence is no later
        //    than `to` AND the recurrence horizon (recurrenceUntilDate) is no earlier than `from`. The
        //    aggregator expands the actual occurrences afterwards.
        // Eager-fetch exceptions so the aggregator's expansion-time filter does not N+1.
        return $this->createQueryBuilder('a')
            ->addSelect('c', 'e')
            ->leftJoin('a.creator', 'c')
            ->leftJoin('a.exceptions', 'e')
            ->where('a.bandSpace = :bandSpace')
            ->andWhere(
                '(a.recurrenceFrequency IS NULL AND a.eventDatetime <= :to AND COALESCE(a.endDatetime, a.eventDatetime) >= :from)' .
                ' OR (a.recurrenceFrequency IS NOT NULL AND a.eventDatetime <= :to AND a.recurrenceUntilDate >= :from)'
            )
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

    /**
     * @param string[] $ids
     * @return AgendaEntry[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // The band space comes along because the notification enricher reads it on every row to work
        // out whether the reader may still be shown a live title and date, and one proxy load per
        // entry would undo the point of fetching them in a single query.
        return $this->createQueryBuilder('a')
            ->innerJoin('a.bandSpace', 'bs')
            ->addSelect('bs')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Command palette search. Matches the entry rows themselves, so a recurring entry is one hit
     * rather than one per occurrence: occurrences are expanded at read time by AgendaAggregator,
     * which needs a date window the palette has no reason to invent.
     *
     * @return AgendaEntry[]
     */
    public function searchByBandSpace(BandSpace $bandSpace, string $search, int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.bandSpace = :bandSpace')
            ->andWhere('LOWER(a.title) LIKE :search OR LOWER(a.description) LIKE :search OR LOWER(a.location) LIKE :search')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('a.eventDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

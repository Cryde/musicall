<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\AvailabilityAnswer;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<AgendaEntryAvailability>
 */
class AgendaEntryAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgendaEntryAvailability::class);
    }

    /**
     * Records or replaces one member's answer in a single statement, so two taps or two devices
     * answering at once cannot both insert and trip the unique key (a 500 that would also close the
     * EntityManager). Raw SQL because DQL has no upsert; the row has no lifecycle to bypass.
     */
    public function upsert(AgendaEntry $entry, DateTimeImmutable $occurrenceDate, BandSpaceMembership $membership, AvailabilityAnswer $answer): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO agenda_entry_availability (id, agenda_entry_id, occurrence_date, membership_id, answer, answered_at)'
            . ' VALUES (:id, :entry, :date, :membership, :answer, :answeredAt)'
            . ' ON DUPLICATE KEY UPDATE answer = VALUES(answer), answered_at = VALUES(answered_at)',
            [
                'id' => Uuid::uuid4()->toString(),
                'entry' => (string) $entry->id,
                'date' => $occurrenceDate->format('Y-m-d'),
                'membership' => (string) $membership->id,
                'answer' => $answer->value,
                'answeredAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * The answers still ahead of an entry, for when its start moves: members agreed to the old slot.
     * Past dates are left alone, being the record of who came. A bulk delete: the rows have no
     * lifecycle events and nothing else holds them.
     */
    public function deleteUpcomingByEntry(AgendaEntry $entry): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM ' . AgendaEntryAvailability::class . ' a WHERE a.agendaEntry = :entry AND a.occurrenceDate >= :today')
            ->setParameter('entry', (string) $entry->id)
            ->setParameter('today', (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'))
            ->execute();
    }

    /**
     * @return AgendaEntryAvailability[]
     */
    public function findForOccurrence(AgendaEntry $entry, DateTimeImmutable $occurrenceDate): array
    {
        return $this->findBy(['agendaEntry' => $entry, 'occurrenceDate' => $occurrenceDate]);
    }

    /**
     * Every answer on these entries between two dates, for the agenda feed: one query for the window
     * rather than one per occurrence.
     *
     * @param AgendaEntry[] $entries
     *
     * @return AgendaEntryAvailability[]
     */
    public function findForEntriesBetween(array $entries, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if ($entries === []) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->where('a.agendaEntry IN (:entries)')
            ->andWhere('a.occurrenceDate BETWEEN :from AND :to')
            ->setParameter('entries', $entries)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}

<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryException;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgendaEntryException>
 */
class AgendaEntryExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgendaEntryException::class);
    }

    /**
     * Every cancelled occurrence of an entry. Read through the repository rather than through
     * `AgendaEntry::$exceptions`, which is the inverse side of the association and therefore only
     * ever holds what has already been loaded from the database.
     *
     * @return AgendaEntryException[]
     */
    public function findByEntry(AgendaEntry $entry): array
    {
        return $this->findBy(['agendaEntry' => $entry]);
    }

    public function findOneByEntryAndDate(AgendaEntry $entry, DateTimeImmutable $occurrenceDate): ?AgendaEntryException
    {
        return $this->findOneBy([
            'agendaEntry' => $entry,
            'occurrenceDate' => $occurrenceDate->setTime(0, 0),
        ]);
    }
}

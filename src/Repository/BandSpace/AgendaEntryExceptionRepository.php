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

    public function findOneByEntryAndDate(AgendaEntry $entry, DateTimeImmutable $occurrenceDate): ?AgendaEntryException
    {
        return $this->findOneBy([
            'agendaEntry' => $entry,
            'occurrenceDate' => $occurrenceDate->setTime(0, 0),
        ]);
    }
}

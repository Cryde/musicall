<?php declare(strict_types=1);

namespace App\Repository\Attribute;

use App\Entity\Attribute\Instrument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Instrument>
 */
class InstrumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Instrument::class);
    }

    /**
     * A non-uuid id simply matches nothing rather than reaching the uuid column, because
     * setParameter with no explicit type binds the plain string. That safety does not survive a
     * switch to findBy(['id' => $ids]), which binds through the column type and throws
     * ValueNotConvertible, surfacing as a 500 where the caller wants a 422.
     *
     * @param list<string> $ids
     * @return Instrument[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->where('i.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

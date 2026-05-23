<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Setlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setlist>
 */
class SetlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setlist::class);
    }

    /**
     * @return Setlist[]
     */
    public function findByBandSpace(BandSpace $bandSpace, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('s.creationDatetime', 'DESC');

        if (!$includeArchived) {
            $qb->andWhere('s.archiveDatetime IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Direct id lookup does NOT filter archived setlists - clients need
     * archived setlists to render in activity logs / restore flows.
     */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?Setlist
    {
        return $this->createQueryBuilder('s')
            ->addSelect('i', 'song')
            ->leftJoin('s.items', 'i')
            ->leftJoin('i.song', 'song')
            ->where('s.id = :id')
            ->andWhere('s.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $ids
     * @return Setlist[]
     */
    public function findByIdsAndBandSpace(array $ids, BandSpace $bandSpace): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->andWhere('s.bandSpace = :bandSpace')
            ->setParameter('ids', $ids)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getResult();
    }

    /**
     * Single aggregate query - sum of (durationOverride OR song.referenceDuration OR 0).
     */
    public function totalDurationSeconds(Setlist $setlist): int
    {
        $sum = $this->getEntityManager()
            ->createQuery(
                'SELECT SUM(COALESCE(i.durationOverride, song.referenceDuration, 0))
                 FROM App\Entity\BandSpace\SetlistItem i
                 LEFT JOIN i.song song
                 WHERE i.setlist = :setlist'
            )
            ->setParameter('setlist', $setlist)
            ->getSingleScalarResult();

        return (int) ($sum ?? 0);
    }
}

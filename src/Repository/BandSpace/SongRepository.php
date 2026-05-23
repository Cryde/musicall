<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Song;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Song>
 */
class SongRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Song::class);
    }

    /**
     * @return Song[]
     */
    public function findByBandSpace(BandSpace $bandSpace, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('s.title', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('s.archiveDatetime IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Direct id lookup does NOT filter archived songs - clients need the
     * archived song to render in setlist items / file detail drawers.
     */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?Song
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $ids
     * @return Song[]
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
}

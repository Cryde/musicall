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
    public function findByBandSpace(BandSpace $bandSpace, bool $archivedOnly = false): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.bandSpace = :bandSpace')
            ->andWhere($archivedOnly ? 's.archiveDatetime IS NOT NULL' : 's.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

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

    /**
     * Command palette search. Notes are plain text on this entity, unlike a band space note's body,
     * so they can be matched directly.
     *
     * @return Song[]
     */
    public function searchByBandSpace(BandSpace $bandSpace, string $search, int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.bandSpace = :bandSpace')
            ->andWhere('s.archiveDatetime IS NULL')
            ->andWhere('LOWER(s.title) LIKE :search OR LOWER(s.notes) LIKE :search')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('s.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

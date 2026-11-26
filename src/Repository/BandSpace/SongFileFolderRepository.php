<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\SongFileFolder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SongFileFolder>
 */
class SongFileFolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongFileFolder::class);
    }

    /**
     * @return SongFileFolder[]
     */
    public function findBySong(Song $song): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.song = :song')
            ->setParameter('song', $song)
            ->orderBy('f.name', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every song folder of the space: the nodes the Files tree unfolds each song into, and the names
     * offered when a member names a new folder.
     *
     * @return SongFileFolder[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('s')
            ->join('f.song', 's')
            ->where('s.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('f.name', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndSong(string $id, Song $song): ?SongFileFolder
    {
        return $this->createQueryBuilder('f')
            ->where('f.id = :id')
            ->andWhere('f.song = :song')
            ->setParameter('id', $id)
            ->setParameter('song', $song)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function siblingNameExists(Song $song, ?SongFileFolder $parent, string $name, ?SongFileFolder $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.song = :song')
            ->andWhere('LOWER(f.name) = :name')
            ->setParameter('song', $song)
            ->setParameter('name', mb_strtolower(trim($name)));

        if ($parent instanceof SongFileFolder) {
            $qb->andWhere('f.parent = :parent')->setParameter('parent', $parent);
        } else {
            $qb->andWhere('f.parent IS NULL');
        }

        if ($exclude instanceof SongFileFolder) {
            $qb->andWhere('f.id != :excludeId')->setParameter('excludeId', $exclude->id);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }
}

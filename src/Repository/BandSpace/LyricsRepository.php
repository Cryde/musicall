<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lyrics>
 */
class LyricsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lyrics::class);
    }

    /**
     * Live lyrics or the Corbeille, never both, with their song joined so the list asks once.
     *
     * @return Lyrics[]
     */
    public function findByBandSpace(BandSpace $bandSpace, bool $archivedOnly = false): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('s')
            ->leftJoin('l.song', 's')
            ->where('l.bandSpace = :bandSpace')
            ->andWhere($archivedOnly ? 'l.archiveDatetime IS NOT NULL' : 'l.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('l.title', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Archived lyrics included, which is what the Corbeille and its restore need. */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?Lyrics
    {
        return $this->createQueryBuilder('l')
            ->addSelect('s')
            ->leftJoin('l.song', 's')
            ->where('l.id = :id')
            ->andWhere('l.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySong(Song $song): ?Lyrics
    {
        return $this->findOneBy(['song' => $song]);
    }

    /**
     * @param string[] $songIds
     *
     * @return array<string, Lyrics> keyed by song id
     */
    public function findBySongIds(array $songIds): array
    {
        if ($songIds === []) {
            return [];
        }

        /** @var Lyrics[] $rows */
        $rows = $this->createQueryBuilder('l')
            ->where('l.song IN (:songIds)')
            ->setParameter('songIds', $songIds)
            ->getQuery()
            ->getResult();

        $bySong = [];
        foreach ($rows as $lyrics) {
            if ($lyrics->song instanceof Song) {
                $bySong[(string) $lyrics->song->id] = $lyrics;
            }
        }

        return $bySong;
    }

    /**
     * Which of these songs have lyrics written down, without loading the lyrics themselves.
     *
     * @param string[] $songIds
     *
     * @return array<string, true>
     */
    public function findSongIdsWithLyrics(array $songIds): array
    {
        if ($songIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.song) AS songId')
            ->where('l.song IN (:songIds)')
            ->andWhere('l.content IS NOT NULL')
            ->setParameter('songIds', $songIds)
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys(array_map(strval(...), $rows), true);
    }
}

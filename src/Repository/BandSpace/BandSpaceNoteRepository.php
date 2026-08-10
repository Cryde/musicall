<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceNote>
 */
class BandSpaceNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceNote::class);
    }

    /**
     * @return BandSpaceNote[]
     */
    public function findByBandSpace(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('n.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every note that has a body, oldest first, with its band space already loaded.
     *
     * Deliberately unfiltered. The one shot encoding repair of #859 has to read inside the JSON to
     * tell a note the old read path mangled from one whose author typed the entities by hand, and no
     * index reaches in there, so narrowing on a LIKE would make the scan count a lie while still
     * reading the whole table. Notes are a handful per band space, so the set fits in memory.
     *
     * The band space is join fetched rather than merely joined: the repair names every note it
     * reports by its space, so a lazy association would mean one extra query per reported note.
     *
     * @return BandSpaceNote[]
     */
    public function findAllWithContent(): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('bs')
            ->join('n.bandSpace', 'bs')
            ->where('n.content IS NOT NULL')
            ->orderBy('n.creationDatetime', 'ASC')
            ->addOrderBy('n.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceNote
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The note plus every note underneath it, whatever the depth, as id => title.
     *
     * Deleting a note takes its whole subtree with it: band_space_note.parent_id is ON DELETE CASCADE,
     * so the descendants vanish at database level without Doctrine ever loading them. Anything that has
     * to clean up after those rows (their file attachments) needs the ids and titles beforehand.
     *
     * Raw SQL because a recursive CTE has no DQL equivalent; the alternative is one query per level.
     *
     * @return array<string, string>
     */
    public function findSelfAndDescendantTitles(BandSpaceNote $note): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE descendants(id, title) AS (
                SELECT id, title FROM band_space_note WHERE id = :rootId
                UNION ALL
                SELECT n.id, n.title
                FROM band_space_note n
                INNER JOIN descendants d ON n.parent_id = d.id
            )
            SELECT id, title FROM descendants
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            ['rootId' => (string) $note->id],
        )->fetchAllAssociative();

        $titles = [];
        foreach ($rows as $row) {
            $titles[(string) $row['id']] = (string) $row['title'];
        }

        return $titles;
    }

    public function getNextPosition(BandSpace $bandSpace, ?BandSpaceNote $parent): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('MAX(n.position)')
            ->where('n.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace);

        if ($parent instanceof \App\Entity\BandSpace\BandSpaceNote) {
            $qb->andWhere('n.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('n.parent IS NULL');
        }

        $maxPosition = $qb->getQuery()->getSingleScalarResult();

        return $maxPosition !== null ? ((int) $maxPosition) + 1 : 0;
    }
}

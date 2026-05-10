<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFolder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFolder>
 */
class BandSpaceFolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFolder::class);
    }

    /**
     * @return BandSpaceFolder[]
     */
    public function findTree(BandSpace $bandSpace): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('p')
            ->leftJoin('f.parent', 'p')
            ->where('f.bandSpace = :bandSpace')
            ->setParameter('bandSpace', $bandSpace)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceFolder
    {
        return $this->createQueryBuilder('f')
            ->where('f.id = :id')
            ->andWhere('f.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns the IDs of $folder and every descendant beneath it.
     *
     * @return string[]
     */
    public function findDescendantIds(BandSpaceFolder $folder): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE descendants(id) AS (
                SELECT id FROM band_space_folder WHERE id = :rootId
                UNION ALL
                SELECT bsf.id
                FROM band_space_folder bsf
                INNER JOIN descendants d ON bsf.parent_id = d.id
            )
            SELECT id FROM descendants
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            $sql,
            ['rootId' => (string) $folder->id],
        )->fetchAllAssociative();

        return array_map(static fn (array $row): string => (string) $row['id'], $rows);
    }

    /**
     * Computes the 0-based depth of $folder by walking its parent chain.
     */
    public function computeDepth(?BandSpaceFolder $folder): int
    {
        $depth = 0;
        while ($folder instanceof \App\Entity\BandSpace\BandSpaceFolder && $folder->parent instanceof \App\Entity\BandSpace\BandSpaceFolder) {
            $depth++;
            $folder = $folder->parent;
        }

        return $depth;
    }

    /**
     * True if a sibling folder with the same case-insensitive name already exists.
     */
    public function siblingNameExists(
        BandSpace $bandSpace,
        ?BandSpaceFolder $parent,
        string $name,
        ?BandSpaceFolder $exclude = null,
    ): bool {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.bandSpace = :bandSpace')
            ->andWhere('LOWER(f.name) = :name')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('name', mb_strtolower(trim($name)));

        if (!$parent instanceof \App\Entity\BandSpace\BandSpaceFolder) {
            $qb->andWhere('f.parent IS NULL');
        } else {
            $qb->andWhere('f.parent = :parent')
                ->setParameter('parent', $parent);
        }

        if ($exclude instanceof \App\Entity\BandSpace\BandSpaceFolder) {
            $qb->andWhere('f.id != :excludeId')
                ->setParameter('excludeId', $exclude->id);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }
}

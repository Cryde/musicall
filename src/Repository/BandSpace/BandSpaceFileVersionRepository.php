<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFileVersion>
 */
class BandSpaceFileVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFileVersion::class);
    }

    /**
     * @return BandSpaceFileVersion[]
     */
    public function findByFileNewestFirst(BandSpaceFile $file): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('u')
            ->leftJoin('v.createdBy', 'u')
            ->where('v.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->orderBy('v.versionNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByFileAndVersionNumber(BandSpaceFile $file, int $versionNumber): ?BandSpaceFileVersion
    {
        return $this->createQueryBuilder('v')
            ->where('v.bandSpaceFile = :file')
            ->andWhere('v.versionNumber = :versionNumber')
            ->setParameter('file', $file)
            ->setParameter('versionNumber', $versionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMaxVersionNumber(BandSpaceFile $file): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('MAX(v.versionNumber) AS max_version')
            ->where('v.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->getQuery()
            ->getSingleScalarResult();

        return $result === null ? 0 : (int) $result;
    }

    /**
     * Total bytes used by all versions of all non-archived files in the band.
     */
    public function sumActiveBytesByBandSpace(BandSpace $bandSpace): int
    {
        $sql = <<<'SQL'
            SELECT COALESCE(SUM(v.size), 0) AS total
            FROM band_space_file_version v
            INNER JOIN band_space_file f ON f.id = v.band_space_file_id
            WHERE f.band_space_id = :bandSpaceId
              AND f.archive_datetime IS NULL
        SQL;

        $result = $this->getEntityManager()->getConnection()->fetchOne(
            $sql,
            ['bandSpaceId' => (string) $bandSpace->id],
        );

        return (int) $result;
    }

    /**
     * Active-version byte usage grouped by attached source type. Manual uploads
     * (where attached_source_type IS NULL) are reported as `'manual'`.
     *
     * @return array<int, array{source: string, bytes: int}>
     */
    public function sumActiveBytesByBandSpaceGroupedBySource(BandSpace $bandSpace): array
    {
        $sql = <<<'SQL'
            SELECT COALESCE(f.attached_source_type, 'manual') AS source, COALESCE(SUM(v.size), 0) AS bytes
            FROM band_space_file_version v
            INNER JOIN band_space_file f ON f.id = v.band_space_file_id
            WHERE f.band_space_id = :bandSpaceId
              AND f.archive_datetime IS NULL
            GROUP BY source
            ORDER BY source ASC
        SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            $sql,
            ['bandSpaceId' => (string) $bandSpace->id],
        );

        return array_map(
            fn (array $row): array => ['source' => (string) $row['source'], 'bytes' => (int) $row['bytes']],
            $rows,
        );
    }
}

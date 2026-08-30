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
     * Total bytes held by every version of one file, archived or not.
     *
     * Restoring a file puts all of it back into the band's quota usage, since sumActiveBytesByBandSpace()
     * counts every version of a non-archived file. This is the amount a restore has to fit.
     */
    public function sumBytesByFile(BandSpaceFile $file): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('COALESCE(SUM(v.size), 0)')
            ->where('v.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
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
     * Active-version byte usage grouped by attached source type. Files with no
     * attachment are reported as `'manual'`. A file attached to multiple sources
     * contributes its total bytes to each source bucket — the sum may exceed
     * the actual band quota usage.
     *
     * @return array<int, array{source: string, bytes: int}>
     */
    public function sumActiveBytesByBandSpaceGroupedBySource(BandSpace $bandSpace): array
    {
        $sql = <<<'SQL'
            SELECT COALESCE(a.source_type, 'manual') AS source,
                   COALESCE(SUM(file_bytes.total), 0) AS bytes
            FROM (
                SELECT f.id AS file_id, COALESCE(SUM(v.size), 0) AS total
                FROM band_space_file f
                INNER JOIN band_space_file_version v ON v.band_space_file_id = f.id
                WHERE f.band_space_id = :bandSpaceId
                  AND f.archive_datetime IS NULL
                GROUP BY f.id
            ) file_bytes
            LEFT JOIN band_space_file_attachment a ON a.band_space_file_id = file_bytes.file_id
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

    /**
     * The bytes a whole bulk restore would put back into the band's usage, in one query.
     *
     * The per file sumBytesByFile() would be one query per row here, and worse, asserting the quota
     * once per file admits each of them against a total the others have not been counted into yet, so
     * a batch can walk a space past its limit one file at a time.
     *
     * @param string[] $fileIds
     */
    public function sumBytesByFileIds(array $fileIds): int
    {
        if (count($fileIds) === 0) {
            return 0;
        }

        return (int) $this->createQueryBuilder('v')
            ->select('COALESCE(SUM(v.size), 0)')
            ->where('IDENTITY(v.bandSpaceFile) IN (:fileIds)')
            ->setParameter('fileIds', $fileIds)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

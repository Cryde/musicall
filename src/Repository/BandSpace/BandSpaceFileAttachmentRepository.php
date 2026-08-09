<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFileAttachment>
 */
class BandSpaceFileAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFileAttachment::class);
    }

    public function findOneByFileAndSource(BandSpaceFile $file, string $sourceType, string $sourceId): ?BandSpaceFileAttachment
    {
        return $this->createQueryBuilder('a')
            ->where('a.bandSpaceFile = :file')
            ->andWhere('a.sourceType = :sourceType')
            ->andWhere('a.sourceId = :sourceId')
            ->setParameter('file', $file)
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceId', $sourceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsForFile(BandSpaceFile $file): bool
    {
        $count = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return BandSpaceFileAttachment[]
     */
    public function findByFile(BandSpaceFile $file): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->orderBy('a.attachedDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The distinct source types each of the given files is attached to. Files with no attachment are
     * absent from the result, so an empty array means "none of them is attached".
     *
     * A projection rather than the rows themselves: the folder cascade only needs to know which files
     * are pinned and by what kind of source, and a subtree can hold far more attachments than the
     * sentence it ends up building has room for.
     *
     * @param string[] $fileIds
     *
     * @return array<string, string[]> file id => distinct source types
     */
    public function findSourceTypesByFileIds(array $fileIds): array
    {
        if (count($fileIds) === 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.bandSpaceFile) AS file_id', 'a.sourceType AS source_type')
            ->where('a.bandSpaceFile IN (:ids)')
            ->groupBy('a.bandSpaceFile', 'a.sourceType')
            ->setParameter('ids', $fileIds)
            ->getQuery()
            ->getArrayResult();

        $sourceTypesByFile = [];
        foreach ($rows as $row) {
            $sourceTypesByFile[(string) $row['file_id']][] = (string) $row['source_type'];
        }

        return $sourceTypesByFile;
    }

    /**
     * @param string[] $fileIds
     *
     * @return array<string, BandSpaceFileAttachment[]> file id => attachments
     */
    public function findByFileIds(array $fileIds): array
    {
        if (count($fileIds) === 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->where('a.bandSpaceFile IN (:ids)')
            ->setParameter('ids', $fileIds)
            ->orderBy('a.attachedDatetime', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row->bandSpaceFile->id][] = $row;
        }

        return $grouped;
    }

    /**
     * Every attachment hanging on the given sources, file fetch-joined because the caller needs the file
     * id of each row it is about to drop.
     *
     * @param string[] $sourceIds
     *
     * @return BandSpaceFileAttachment[]
     */
    public function findBySource(string $sourceType, array $sourceIds): array
    {
        if (count($sourceIds) === 0) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->addSelect('bsf')
            ->innerJoin('a.bandSpaceFile', 'bsf')
            ->where('a.sourceType = :sourceType')
            ->andWhere('a.sourceId IN (:sourceIds)')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceIds', $sourceIds)
            ->orderBy('a.attachedDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Attachments hanging on a single source, archived files left out.
     *
     * Used when a source is copied: the copy should carry the documents the band still sees, and an
     * attachment pointing at a file in the trash is invisible everywhere the copy is rendered, so
     * cloning it would only add a row nobody can act on.
     *
     * @return BandSpaceFileAttachment[]
     */
    public function findActiveBySource(string $sourceType, string $sourceId): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('bsf')
            ->innerJoin('a.bandSpaceFile', 'bsf')
            ->where('a.sourceType = :sourceType')
            ->andWhere('a.sourceId = :sourceId')
            ->andWhere('bsf.archiveDatetime IS NULL')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceId', $sourceId)
            ->orderBy('a.attachedDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Attachments pointing at a source row that no longer exists.
     *
     * source_id carries no foreign key, so nothing at database level can tell these apart from live
     * rows: the caller has to say which entity the ids were supposed to point at. A soft-deleted source
     * (an archived Song, Setlist or Task) still has its row, so it is deliberately NOT an orphan here.
     *
     * @param class-string $sourceEntityClass
     *
     * @return BandSpaceFileAttachment[]
     */
    public function findOrphansBySourceType(string $sourceType, string $sourceEntityClass): array
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT a FROM App\Entity\BandSpace\BandSpaceFileAttachment a
                 WHERE a.sourceType = :sourceType
                 AND NOT EXISTS (SELECT 1 FROM ' . $sourceEntityClass . ' source WHERE source.id = a.sourceId)'
            )
            ->setParameter('sourceType', $sourceType)
            ->getResult();
    }

    /**
     * @param string[] $sourceIds
     *
     * @return array<string, int> source id => unique non-archived file count
     */
    public function countActiveBySourceIds(string $sourceType, array $sourceIds): array
    {
        if (count($sourceIds) === 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.sourceId AS source_id', 'COUNT(DISTINCT a.bandSpaceFile) AS file_count')
            ->innerJoin('a.bandSpaceFile', 'bsf')
            ->where('a.sourceType = :sourceType')
            ->andWhere('a.sourceId IN (:ids)')
            ->andWhere('bsf.archiveDatetime IS NULL')
            ->groupBy('a.sourceId')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('ids', $sourceIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['source_id']] = (int) $row['file_count'];
        }

        return $counts;
    }

    /**
     * Active-attachment counts per source type for the band, distinct by file id.
     * Mirrors the previous countActiveByBandSpaceGroupedBySource shape.
     *
     * @return array<string, int> source => unique file count
     */
    public function countActiveByBandSpaceGroupedBySource(BandSpace $bandSpace): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.sourceType AS source', 'COUNT(DISTINCT a.bandSpaceFile) AS file_count')
            ->innerJoin('a.bandSpaceFile', 'bsf')
            ->where('bsf.bandSpace = :bandSpace')
            ->andWhere('bsf.archiveDatetime IS NULL')
            ->groupBy('a.sourceType')
            ->orderBy('a.sourceType', 'ASC')
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['source']] = (int) $row['file_count'];
        }

        return $counts;
    }
}

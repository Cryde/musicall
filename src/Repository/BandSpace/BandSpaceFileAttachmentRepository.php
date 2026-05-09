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

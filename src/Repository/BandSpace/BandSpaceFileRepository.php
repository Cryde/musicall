<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFolder;
use App\Repository\BandSpace\Filter\BandSpaceFileFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFile>
 */
class BandSpaceFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFile::class);
    }

    /**
     * One page of files, tags and uploader included.
     *
     * @return BandSpaceFile[]
     */
    public function findByBandSpace(BandSpace $bandSpace, BandSpaceFileFilter $filter): array
    {
        $qb = $this->buildBandSpaceQuery($bandSpace, $filter)
            ->addSelect('u', 'f', 'cv', 't')
            ->leftJoin('bsf.createdBy', 'u')
            ->leftJoin('bsf.folder', 'f')
            ->leftJoin('bsf.tags', 't');

        $this->applySort($qb, $filter);

        $qb->setMaxResults($filter->limit)
            ->setFirstResult($filter->offset);

        // Paginator, not getResult(): the tags fetch join turns one file into one row per tag, so a
        // plain LIMIT cuts the window mid file and returns fewer files than asked for. The count
        // beside this one counts distinct files, so the shortfall reads as a page the caller has
        // already seen and the files behind it are never requested again.
        /** @var BandSpaceFile[] $files */
        $files = iterator_to_array(new Paginator($qb->getQuery()), false);

        return $files;
    }

    public function countByBandSpace(BandSpace $bandSpace, BandSpaceFileFilter $filter): int
    {
        $qb = $this->buildBandSpaceQuery($bandSpace, $filter)
            ->select('COUNT(DISTINCT bsf.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Files archived before the cutoff, for app:band-space:purge. Deliberately not scoped to a band
     * space: the command sweeps the whole table. Unbounded on purpose - the daily run only ever sees
     * one day's worth of newly-due files; add a limit here if that stops being true.
     *
     * @return BandSpaceFile[]
     */
    public function findArchivedOlderThan(\DateTimeImmutable $cutoff): array
    {
        return $this->createQueryBuilder('bsf')
            ->where('bsf.archiveDatetime IS NOT NULL')
            ->andWhere('bsf.archiveDatetime <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('bsf.archiveDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceFile
    {
        return $this->createQueryBuilder('bsf')
            ->addSelect('u', 'f', 'cv', 't')
            ->leftJoin('bsf.createdBy', 'u')
            ->leftJoin('bsf.folder', 'f')
            ->leftJoin('bsf.currentVersion', 'cv')
            ->leftJoin('bsf.tags', 't')
            ->where('bsf.id = :id')
            ->andWhere('bsf.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $fileIds
     *
     * @return array<string, int> file id => version count
     */
    public function countVersionsByFileIds(array $fileIds): array
    {
        if (count($fileIds) === 0) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(v.bandSpaceFile) AS file_id', 'COUNT(v.id) AS version_count')
            ->from('App\Entity\BandSpace\BandSpaceFileVersion', 'v')
            ->where('v.bandSpaceFile IN (:fileIds)')
            ->groupBy('v.bandSpaceFile')
            ->setParameter('fileIds', $fileIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['file_id']] = (int) $row['version_count'];
        }

        return $counts;
    }

    /**
     * @param string[] $tagIds
     *
     * @return array<string, int> tag id => active file count
     */
    public function countByTagIds(array $tagIds): array
    {
        if (count($tagIds) === 0) {
            return [];
        }

        $rows = $this->createQueryBuilder('bsf')
            ->select('t.id AS tag_id', 'COUNT(DISTINCT bsf.id) AS file_count')
            ->innerJoin('bsf.tags', 't')
            ->where('t.id IN (:tagIds)')
            ->andWhere('bsf.archiveDatetime IS NULL')
            ->groupBy('t.id')
            ->setParameter('tagIds', $tagIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['tag_id']] = (int) $row['file_count'];
        }

        return $counts;
    }

    /**
     * Active-file counts grouped by attached source type. Manual files
     * (no attached source) are not returned. Order: source ASC.
     *
     * @return array<string, int> source => file count
     */
    /**
     * @return array<int, array{id: string, name: string}> root → leaf
     */
    public function buildFolderPath(?BandSpaceFolder $folder): array
    {
        $path = [];
        while ($folder instanceof \App\Entity\BandSpace\BandSpaceFolder) {
            array_unshift($path, ['id' => (string) $folder->id, 'name' => $folder->name]);
            $folder = $folder->parent;
        }

        return $path;
    }

    private function buildBandSpaceQuery(BandSpace $bandSpace, BandSpaceFileFilter $filter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('bsf')
            ->leftJoin('bsf.currentVersion', 'cv')
            ->where('bsf.bandSpace = :bandSpace')
            // Archived and active are two disjoint views, never merged: the trash lists what the normal
            // collection hides, so no caller can accidentally mix a deleted file into a live listing.
            ->andWhere($filter->archivedOnly ? 'bsf.archiveDatetime IS NOT NULL' : 'bsf.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace);

        if ($filter->folderId !== null) {
            $qb->andWhere('bsf.folder = :folderId')
                ->setParameter('folderId', $filter->folderId);
        }

        if ($filter->tagId !== null) {
            $qb->andWhere(':tagId MEMBER OF bsf.tags')
                ->setParameter('tagId', $filter->tagId);
        }

        if ($filter->source !== null) {
            if ($filter->source === 'manual') {
                // Standalone files have no attachment row.
                $qb->andWhere(
                    'NOT EXISTS (SELECT 1 FROM App\\Entity\\BandSpace\\BandSpaceFileAttachment attMan '
                    . 'WHERE attMan.bandSpaceFile = bsf)'
                );
            } else {
                $qb->andWhere(
                    'EXISTS (SELECT 1 FROM App\\Entity\\BandSpace\\BandSpaceFileAttachment attSrc '
                    . 'WHERE attSrc.bandSpaceFile = bsf AND attSrc.sourceType = :source'
                    . ($filter->sourceId !== null ? ' AND attSrc.sourceId = :sourceId' : '')
                    . ')'
                )->setParameter('source', $filter->source);

                if ($filter->sourceId !== null) {
                    $qb->setParameter('sourceId', $filter->sourceId);
                }
            }
        } elseif ($filter->sourceId !== null) {
            // sourceId without source: scope by id only.
            $qb->andWhere(
                'EXISTS (SELECT 1 FROM App\\Entity\\BandSpace\\BandSpaceFileAttachment attId '
                . 'WHERE attId.bandSpaceFile = bsf AND attId.sourceId = :sourceId)'
            )->setParameter('sourceId', $filter->sourceId);
        }

        $trimmedQuery = $filter->query !== null ? trim($filter->query) : '';
        if ($trimmedQuery !== '') {
            $qb->andWhere('LOWER(bsf.originalName) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($trimmedQuery) . '%');
        }

        if ($filter->mime !== null && $filter->mime !== '') {
            $qb->andWhere('cv.mimeType LIKE :mime')
                ->setParameter('mime', $filter->mime . '%');
        }

        if ($filter->uploaderId !== null) {
            $qb->andWhere('bsf.createdBy = :uploaderId')
                ->setParameter('uploaderId', $filter->uploaderId);
        }

        return $qb;
    }

    private function applySort(QueryBuilder $qb, BandSpaceFileFilter $filter): void
    {
        $direction = strtolower($filter->order) === 'asc' ? 'ASC' : 'DESC';

        match ($filter->sort) {
            'name' => $qb->orderBy('bsf.originalName', $direction),
            'size' => $qb->orderBy('cv.size', $direction),
            default => $qb->orderBy('bsf.creationDatetime', $direction),
        };

        // Ties are the rule rather than the exception here: a batch upload stamps one creation
        // datetime on every file in it, and two files can share a size or a name. Without a total
        // order the database may put a tied row on two consecutive pages and on neither of them, so
        // paging would quietly drop files. The id settles every tie.
        $qb->addOrderBy('bsf.id', $direction);
    }
}

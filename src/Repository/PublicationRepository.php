<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    public function createCollectionQueryBuilder(?string $subCategorySlug, ?int $subCategoryType, string $orderDirection, ?string $tagSlug = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('publication')
            ->select('publication, sub_category, author, cover, vote_cache')
            ->join('publication.subCategory', 'sub_category')
            ->join('publication.author', 'author')
            ->leftJoin('publication.cover', 'cover')
            ->leftJoin('publication.voteCache', 'vote_cache')
            ->where('publication.status = :status')
            ->setParameter('status', Publication::STATUS_ONLINE);

        if ($subCategorySlug !== null) {
            $qb->andWhere('sub_category.slug = :sub_category_slug')
               ->setParameter('sub_category_slug', $subCategorySlug);
        }

        if ($subCategoryType !== null) {
            $qb->andWhere('sub_category.type = :sub_category_type')
               ->setParameter('sub_category_type', $subCategoryType);
        }

        if ($tagSlug !== null) {
            $qb->innerJoin('publication.tags', 'tag', 'WITH', 'tag.slug = :tag_slug')
               ->setParameter('tag_slug', $tagSlug);
        }

        $qb->orderBy('publication.publicationDatetime', strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC');

        return $qb;
    }

    /**
     * @return Publication|null
     */
    public function findOneVideo(string $videoId): ?object
    {
        return $this->findOneBy(['content' => $videoId, 'type' => Publication::TYPE_VIDEO]);
    }

    /**
     * @return float|int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findOldCourseByOldId(int $oldId): mixed
    {
        return $this->createQueryBuilder('publication')
            ->join('publication.subCategory', 'sub_category')
            ->where('publication.oldPublicationId = :id')
            ->andWhere('sub_category.type = :type')
            ->setParameter('id', $oldId)
            ->setParameter('type', PublicationSubCategory::TYPE_COURSE)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @return Publication[]
     */
    public function getBySearchTerm(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('publication')
            ->where('publication.status = :status')
            ->andWhere('MATCH_AGAINST(publication.title, publication.shortDescription, publication.content) AGAINST(:term) > 0')
            ->andWhere('publication.cover IS NOT NULL')
            ->setParameter('term', $term)
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Publication[]
     */
    public function findLastPublications(int $limit = 4): array
    {
        return $this->createQueryBuilder('publication')
            ->select('publication, sub_category, cover')
            ->join('publication.subCategory', 'sub_category')
            ->leftJoin('publication.cover', 'cover')
            ->where('publication.status = :status')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Publication[]
     */
    public function findRelatedPublications(Publication $publication, int $limit = 4): array
    {
        return $this->createQueryBuilder('publication')
            ->select('publication, sub_category, cover, author')
            ->join('publication.subCategory', 'sub_category')
            ->join('publication.author', 'author')
            ->leftJoin('publication.cover', 'cover')
            ->where('publication.status = :status')
            ->andWhere('publication.id != :currentId')
            ->andWhere('sub_category.id = :subCategoryId')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('currentId', $publication->id)
            ->setParameter('subCategoryId', $publication->subCategory->id)
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Publication IDs sharing at least one tag with $publication, ranked by shared-tag
     * count DESC then publicationDatetime DESC. Returns IDs only so the result is safe
     * to cache (no per-user state).
     *
     * @param int[] $tagIds
     *
     * @return int[]
     */
    public function findRelatedIdsByTags(int $currentPublicationId, array $tagIds, int $limit): array
    {
        if ($tagIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('publication')
            ->select('publication.id AS id, COUNT(shared_tag.id) AS shared_count, publication.publicationDatetime AS pub_datetime')
            ->innerJoin('publication.tags', 'shared_tag', 'WITH', 'shared_tag.id IN (:tagIds)')
            ->where('publication.status = :status')
            ->andWhere('publication.id != :currentId')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('currentId', $currentPublicationId)
            ->setParameter('tagIds', $tagIds)
            ->groupBy('publication.id')
            ->orderBy('shared_count', 'DESC')
            ->addOrderBy('pub_datetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Same-subcategory fallback for related publications. Returns IDs only.
     *
     * @param int[] $excludeIds
     *
     * @return int[]
     */
    public function findRelatedIdsBySubCategory(int $currentPublicationId, int $subCategoryId, int $limit, array $excludeIds = []): array
    {
        $qb = $this->createQueryBuilder('publication')
            ->select('publication.id')
            ->join('publication.subCategory', 'sub_category')
            ->where('publication.status = :status')
            ->andWhere('publication.id != :currentId')
            ->andWhere('sub_category.id = :subCategoryId')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('currentId', $currentPublicationId)
            ->setParameter('subCategoryId', $subCategoryId)
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit);

        if ($excludeIds !== []) {
            $qb->andWhere('publication.id NOT IN (:exclude)')->setParameter('exclude', $excludeIds);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Load online publications by id list, preserving the order of $ids. Joins relations
     * needed by the LIST serialization group (subCategory, cover, author, voteCache).
     *
     * @param int[] $ids
     *
     * @return Publication[]
     */
    public function findOnlineByIdsOrdered(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $entities = $this->createQueryBuilder('publication')
            ->select('publication, sub_category, cover, author, vote_cache')
            ->join('publication.subCategory', 'sub_category')
            ->join('publication.author', 'author')
            ->leftJoin('publication.cover', 'cover')
            ->leftJoin('publication.voteCache', 'vote_cache')
            ->where('publication.status = :status')
            ->andWhere('publication.id IN (:ids)')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($entities as $entity) {
            $byId[(int) $entity->id] = $entity;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * Latest online publications excluding the given id, optionally narrowed by
     * subCategory type (1 = publication, 2 = course). IDs only — safe to cache.
     *
     * @return int[]
     */
    public function findLatestIdsExcluding(?int $excludeId, int $limit, ?int $subCategoryType = null): array
    {
        $qb = $this->createQueryBuilder('publication')
            ->select('publication.id')
            ->where('publication.status = :status')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit);

        if ($excludeId !== null) {
            $qb->andWhere('publication.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        if ($subCategoryType !== null) {
            $qb->join('publication.subCategory', 'sub_category')
               ->andWhere('sub_category.type = :subCategoryType')
               ->setParameter('subCategoryType', $subCategoryType);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Count publications created within a date range (only ONLINE status).
     */
    public function countPublicationsSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('publication')
            ->select('COUNT(publication.id)')
            ->where('publication.publicationDatetime >= :since')
            ->andWhere('publication.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count publications grouped by date within a range (only ONLINE status).
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countPublicationsByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(publication_datetime) AS date_label, COUNT(id) AS count
             FROM publication
             WHERE publication_datetime >= :from AND publication_datetime < :to
               AND status = :status
             GROUP BY DATE(publication_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'status' => Publication::STATUS_ONLINE]
        );

        return array_map(
            fn (array $row): array => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    /**
     * Count total online publications.
     */
    public function countTotalOnlinePublications(): int
    {
        return (int) $this->createQueryBuilder('publication')
            ->select('COUNT(publication.id)')
            ->where('publication.status = :status')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get publication counts grouped by subcategory type.
     *
     * @return array<string, int>
     */
    public function countBySubCategoryType(): array
    {
        $results = $this->createQueryBuilder('publication')
            ->select('sub_category.slug as category_slug, COUNT(publication.id) as count')
            ->join('publication.subCategory', 'sub_category')
            ->where('publication.status = :status')
            ->groupBy('sub_category.slug')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['category_slug']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get publication counts grouped by subcategory type within a date range.
     *
     * @return array<string, int>
     */
    public function countBySubCategoryTypeBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $results = $this->createQueryBuilder('publication')
            ->select('sub_category.slug as category_slug, COUNT(publication.id) as count')
            ->join('publication.subCategory', 'sub_category')
            ->where('publication.status = :status')
            ->andWhere('publication.publicationDatetime >= :from')
            ->andWhere('publication.publicationDatetime < :to')
            ->groupBy('sub_category.slug')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['category_slug']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get top publications by view count within a date range.
     *
     * @return array<int, array{id: int, title: string, views: int, type: string}>
     */
    public function findTopPublicationsByViewsBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('publication')
            ->select('publication.id, publication.title, publication.type, COALESCE(vc.count, 0) as views')
            ->leftJoin('publication.viewCache', 'vc')
            ->where('publication.status = :status')
            ->andWhere('publication.publicationDatetime >= :from')
            ->andWhere('publication.publicationDatetime < :to')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(function (array $row): array {
            return [
                'id' => $row['id'],
                'title' => $row['title'],
                'views' => (int) $row['views'],
                'type' => $row['type'] === Publication::TYPE_VIDEO ? 'video' : 'text',
            ];
        }, $results);
    }

    /**
     * Count publications grouped by format (text vs video) within a date range (only ONLINE status).
     *
     * @return array<string, int>
     */
    public function countByFormatBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $results = $this->createQueryBuilder('publication')
            ->select('publication.type, COUNT(publication.id) as count')
            ->where('publication.status = :status')
            ->andWhere('publication.publicationDatetime >= :from')
            ->andWhere('publication.publicationDatetime < :to')
            ->groupBy('publication.type')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $label = $row['type'] === Publication::TYPE_VIDEO ? Publication::TYPE_VIDEO_LABEL : Publication::TYPE_TEXT_LABEL;
            $counts[$label] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get top publications by view count for a given period.
     *
     * @return array<int, array{id: int, title: string, views: int, type: string}>
     */
    public function findTopPublicationsByViews(\DateTimeImmutable $since, int $limit = 5): array
    {
        $results = $this->createQueryBuilder('publication')
            ->select('publication.id, publication.title, publication.type, COALESCE(vc.count, 0) as views')
            ->leftJoin('publication.viewCache', 'vc')
            ->where('publication.status = :status')
            ->andWhere('publication.publicationDatetime >= :since')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('since', $since)
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(function (array $row): array {
            return [
                'id' => $row['id'],
                'title' => $row['title'],
                'views' => (int) $row['views'],
                'type' => $row['type'] === Publication::TYPE_VIDEO ? 'video' : 'text',
            ];
        }, $results);
    }
}

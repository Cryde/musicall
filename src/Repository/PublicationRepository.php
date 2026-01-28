<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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

    /**
     *
     * @return int|mixed|string
     */
    public function findByTitleAndStatusAndType(string $title, int $status, int $type, int $limit = 10): mixed
    {
        return $this->createQueryBuilder('publication')
            ->where('publication.status = :status')
            ->andWhere('publication.type = :type')
            ->andWhere('publication.title like :title')
            ->setParameter('status', $status)
            ->setParameter('type', $type)
            ->setParameter('title', '%' . $title . '%')
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Publication|null
     */
    public function findOneVideo(string $videoId)
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
            ->setParameter('currentId', $publication->getId())
            ->setParameter('subCategoryId', $publication->getSubCategory()->getId())
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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

        return array_map(function ($row) {
            return [
                'id' => $row['id'],
                'title' => $row['title'],
                'views' => (int) $row['views'],
                'type' => $row['type'] === Publication::TYPE_VIDEO ? 'video' : 'text',
            ];
        }, $results);
    }
}

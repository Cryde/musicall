<?php

declare(strict_types=1);

namespace App\Repository\Publication;

use App\Entity\Publication\Tag;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findOneBySlug(string $slug): ?Tag
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @param string[] $slugs
     *
     * @return Tag[]
     */
    public function findBySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tag[]
     */
    public function searchByLabel(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.label LIKE :term')
            ->setParameter('term', $term . '%')
            ->orderBy('t.label', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPublicationsForTag(int $tagId): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM map_publication_tag WHERE tag_id = :id', ['id' => $tagId]);
    }

    /**
     * Top N tags ranked by associated-publication count DESC then label ASC. Returned
     * `Tag` instances are unmanaged read-only views (see `findAllWithPublicationCount`).
     * Tags with zero publications are excluded.
     *
     * @return array<int, array{tag: Tag, count: int}>
     */
    public function findPopularWithPublicationCount(int $limit): array
    {
        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                'SELECT t.id, t.label, t.slug, t.creation_datetime, COUNT(mpt.tag_id) AS cnt
                 FROM tag t
                 INNER JOIN map_publication_tag mpt ON mpt.tag_id = t.id
                 GROUP BY t.id, t.label, t.slug, t.creation_datetime
                 ORDER BY cnt DESC, t.label ASC
                 LIMIT :lim',
                ['lim' => $limit],
                ['lim' => ParameterType::INTEGER],
            );

        return array_map(static function (array $row): array {
            $tag = new Tag();
            $tag->id = (int) $row['id'];
            $tag->label = (string) $row['label'];
            $tag->slug = (string) $row['slug'];
            $tag->creationDatetime = new DateTime((string) $row['creation_datetime']);

            return ['tag' => $tag, 'count' => (int) $row['cnt']];
        }, $rows);
    }

    /**
     * Returned `Tag` instances are unmanaged (hydrated by hand from a native LEFT JOIN
     * with `map_publication_tag`). They are read-only views — do not persist them.
     *
     * @return array<int, array{tag: Tag, count: int}>
     */
    public function findAllWithPublicationCount(): array
    {
        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                'SELECT t.id, t.label, t.slug, t.creation_datetime, COUNT(mpt.tag_id) AS cnt
                 FROM tag t
                 LEFT JOIN map_publication_tag mpt ON mpt.tag_id = t.id
                 GROUP BY t.id, t.label, t.slug, t.creation_datetime
                 ORDER BY t.label ASC'
            );

        return array_map(static function (array $row): array {
            $tag = new Tag();
            $tag->id = (int) $row['id'];
            $tag->label = (string) $row['label'];
            $tag->slug = (string) $row['slug'];
            $tag->creationDatetime = new DateTime((string) $row['creation_datetime']);

            return ['tag' => $tag, 'count' => (int) $row['cnt']];
        }, $rows);
    }
}

<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumTopic>
 */
class ForumTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumTopic::class);
    }

    public function createQueryBuilderByForumSlug(string $forumSlug): QueryBuilder
    {
        return $this->createQueryBuilder('ft')
            ->innerJoin('ft.forum', 'f')
            ->leftJoin('ft.lastPost', 'lp')
            ->leftJoin('lp.creator', 'lpc')
            ->leftJoin('lpc.profile', 'lpcp')
            ->innerJoin('ft.author', 'a')
            ->innerJoin('a.profile', 'ap')
            // Both profiles because each row names its author and its last poster (#1118).
            ->addSelect('lp', 'lpc', 'lpcp', 'a', 'ap')
            ->where('f.slug = :forumSlug')
            ->setParameter('forumSlug', $forumSlug)
            ->orderBy('ft.type', 'DESC')
            ->addOrderBy('ft.creationDatetime', 'DESC');
    }

    /** The locale of the topic's forum category, alone: all its hreflang links need. */
    public function findLocaleBySlug(string $slug): ?string
    {
        $locale = $this->createQueryBuilder('topic')
            ->select('category.locale')
            ->innerJoin('topic.forum', 'forum')
            ->innerJoin('forum.forumCategory', 'category')
            ->where('topic.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        return is_string($locale) ? $locale : null;
    }

    public function countBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('ft')
            ->select('COUNT(ft.id)')
            ->where('ft.creationDatetime >= :from')
            ->andWhere('ft.creationDatetime < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ForumTopic[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('ft')
            ->innerJoin('ft.author', 'a')
            ->addSelect('a')
            ->orderBy('ft.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * The topics that moved last, across every forum of a locale: by their last post, which a new topic has
     * too as its opening post, and by their creation for one that somehow has none.
     *
     * @return ForumTopic[]
     */
    public function findRecentlyActive(string $locale, int $limit): array
    {
        return $this->createQueryBuilder('ft')
            ->innerJoin('ft.forum', 'f')
            ->addSelect('f')
            ->innerJoin('f.forumCategory', 'fc')
            ->leftJoin('ft.lastPost', 'lp')
            ->addSelect('lp')
            ->addSelect('COALESCE(lp.creationDatetime, ft.creationDatetime) AS HIDDEN lastActivity')
            ->where('fc.locale = :locale')
            ->setParameter('locale', $locale)
            ->orderBy('lastActivity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Full-text search across topic titles and post bodies, in the forums of one
     * locale. Returns one row per matching topic, ranked: title hits first
     * (highest MATCH score), then body-only hits, both tiers tie-broken by
     * creation_datetime DESC.
     *
     * Native SQL because the OR + correlated EXISTS over two MATCH AGAINST
     * predicates is awkward in DQL and we don't need entity hydration here -
     * the caller wires the rows into a search-result DTO.
     *
     * Natural-language mode is used (no IN BOOLEAN MODE) so special characters
     * in the user term are treated as whitespace - no escape needed.
     *
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function searchPaginated(string $term, string $locale, int $page, int $limit): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;

        $forumJoinSql = '
            INNER JOIN forum f             ON f.id = ft.forum_id
            INNER JOIN forum_category fc   ON fc.id = f.forum_category_id
        ';
        $whereSql = '
            fc.locale = :locale
            AND (
                MATCH(ft.title) AGAINST (:term) > 0
                OR EXISTS (
                    SELECT 1 FROM forum_post fp
                    WHERE fp.topic_id = ft.id
                      AND MATCH(fp.content) AGAINST (:term) > 0
                )
            )
        ';

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM forum_topic ft $forumJoinSql WHERE $whereSql",
            ['term' => $term, 'locale' => $locale],
        );

        if ($total === 0) {
            return ['total' => 0, 'rows' => []];
        }

        $rows = $conn->fetchAllAssociative(
            "
            SELECT
                ft.id                       AS topic_id,
                ft.slug                     AS topic_slug,
                ft.title                    AS topic_title,
                ft.post_number              AS topic_post_number,
                ft.creation_datetime        AS topic_creation_datetime,
                f.id                        AS forum_id,
                f.title                     AS forum_title,
                f.slug                      AS forum_slug,
                fc.id                       AS category_id,
                fc.title                    AS category_title,
                lp.creation_datetime        AS last_post_datetime,
                COALESCE(MATCH(ft.title) AGAINST (:term), 0) AS title_score
            FROM forum_topic ft
            $forumJoinSql
            LEFT JOIN forum_post lp        ON lp.id = ft.last_post_id
            WHERE $whereSql
            ORDER BY title_score DESC, ft.creation_datetime DESC
            LIMIT :limit OFFSET :offset
            ",
            ['term' => $term, 'locale' => $locale, 'limit' => $limit, 'offset' => $offset],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * For a batch of topic IDs, return the single best-scoring matching post per
     * topic. Used to source the snippet shown alongside each search result.
     *
     * @param string[] $topicIds
     * @return array<string, array{id: string, content: string}> keyed by topic id
     */
    public function findBestMatchingPostByTopic(array $topicIds, string $term): array
    {
        if ($topicIds === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchAllAssociative(
            '
            SELECT
                fp.topic_id,
                fp.id      AS post_id,
                fp.content,
                MATCH(fp.content) AGAINST (:term) AS score
            FROM forum_post fp
            WHERE fp.topic_id IN (:topicIds)
              AND MATCH(fp.content) AGAINST (:term) > 0
            ORDER BY score DESC
            ',
            ['term' => $term, 'topicIds' => $topicIds],
            ['topicIds' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        $byTopic = [];
        foreach ($rows as $row) {
            $topicId = (string) $row['topic_id'];
            if (isset($byTopic[$topicId])) {
                continue; // already kept the best (rows are score-DESC).
            }
            $byTopic[$topicId] = [
                'id' => (string) $row['post_id'],
                'content' => (string) $row['content'],
            ];
        }

        return $byTopic;
    }
}

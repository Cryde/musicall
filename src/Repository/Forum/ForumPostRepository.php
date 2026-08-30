<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }

    /**
     * The id comes straight off the URL, so it is not necessarily a uuid at all. find() would hand a
     * malformed string to the uuid type and throw before the caller could turn a miss into a 404, which
     * answers garbage with a 500. Reject it here instead and let the caller treat it as not found.
     *
     * The older sibling call sites (ForumPostItemProvider, ForumPostVoteProcessor, ForumPostEditProcessor)
     * still call find() directly and still answer 500 on a malformed id. They want the same treatment,
     * but changing their behaviour is not in the scope of the reporting feature.
     */
    public function findOneById(string $id): ?ForumPost
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->find($id);
    }

    public function createQueryBuilderByTopicSlug(string $topicSlug): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->join('p.topic', 't')
            ->join('p.creator', 'c')
            ->leftJoin('c.profilePicture', 'pp')
            ->addSelect('c', 'pp')
            ->where('t.slug = :slug')
            ->setParameter('slug', $topicSlug)
            ->orderBy('p.creationDatetime', 'ASC');
    }

    /**
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countForumPostsByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM forum_post
             WHERE creation_datetime >= :from AND creation_datetime < :to
             GROUP BY DATE(creation_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        return array_map(
            fn (array $row): array => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    public function countBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.creationDatetime >= :from')
            ->andWhere('p.creationDatetime < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ForumPost[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.topic', 't')
            ->innerJoin('p.creator', 'c')
            ->addSelect('t', 'c')
            ->orderBy('p.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPositionInTopic(ForumPost $post): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.topic = :topic')
            ->andWhere('p.creationDatetime <= :datetime')
            ->setParameter('topic', $post->topic)
            ->setParameter('datetime', $post->creationDatetime)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

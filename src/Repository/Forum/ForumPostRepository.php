<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
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
            fn (array $row) => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
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
}

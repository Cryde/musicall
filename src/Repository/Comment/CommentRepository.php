<?php declare(strict_types=1);

namespace App\Repository\Comment;

use App\Entity\Comment\Comment;
use App\Entity\Comment\CommentThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * A thread's comments with what each row renders: the author, their profile (#1118, for the name)
     * and their picture. Left lazy, a thread of fifty comments costs over a hundred queries.
     *
     * @return Comment[]
     */
    public function findByThreadIdWithAuthors(int $threadId): array
    {
        return $this->createQueryBuilder('comment')
            ->innerJoin('comment.author', 'author')
            ->innerJoin('author.profile', 'author_profile')
            ->leftJoin('author.profilePicture', 'author_picture')
            ->addSelect('author', 'author_profile', 'author_picture')
            ->where('comment.thread = :thread')
            ->setParameter('thread', $threadId)
            ->orderBy('comment.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Distinct authors of every comment in a thread, as User entities (single query).
     *
     * ORM 3 forbids selecting a joined alias alone (`SELECT author ... JOIN comment.author author`),
     * so we select the root + eager-load the author, then de-duplicate by id in PHP.
     *
     * @return User[]
     */
    public function findThreadAuthors(CommentThread $thread): array
    {
        /** @var Comment[] $comments */
        $comments = $this->createQueryBuilder('comment')
            ->innerJoin('comment.author', 'author')
            ->addSelect('author')
            ->where('comment.thread = :thread')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getResult();

        $authorsById = [];
        foreach ($comments as $comment) {
            $authorsById[$comment->author->id] = $comment->author;
        }

        return array_values($authorsById);
    }

    /**
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countCommentsByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM comment
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
}

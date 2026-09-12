<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * One page of a thread's messages, as scalars.
     *
     * Nothing is hydrated, and that is the point. `User` carries three inverse one-to-one profile
     * associations that Doctrine cannot make lazy (#730), so hydrating one author costs four selects
     * and a page of fifty distinct authors costs two hundred. The author is reduced to the fields the
     * renderer needs, and the profile picture to its `imageName`, which is all
     * UserProfilePictureUrlBuilder::buildFromImageName() needs to rebuild the URL. Same shape as
     * MusicianAnnounceRepository::findAuthorsDataForAnnounces(), for the same reason.
     *
     * Newest first, with `id` as the tiebreak #955 made mandatory: `creation_datetime` is second
     * granular, ties are ordinary, and SQL promises nothing about their order, so two page requests
     * planned differently could put a tied row on both sides of a boundary. DESC on both columns lets
     * the planner read `idx_message_thread_creation` backwards, which is physically `(thread_id,
     * creation_datetime, id)`, instead of sorting: measured on a real thread, though the plan depends
     * on the author join staying an eq_ref, so it is a good default rather than a guarantee.
     *
     * @return array<int, array{id: string, content: string, creationDatetime: \DateTimeInterface, authorId: string, authorUsername: string, authorDeletionDatetime: ?\DateTimeImmutable, authorProfilePictureName: ?string}>
     */
    public function findForThread(MessageThread $thread, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('message')
            ->select(
                'message.id AS id',
                'message.content AS content',
                'message.creationDatetime AS creationDatetime',
                'author.id AS authorId',
                'author.username AS authorUsername',
                'author.deletionDatetime AS authorDeletionDatetime',
                'picture.imageName AS authorProfilePictureName',
            )
            ->join('message.author', 'author')
            ->leftJoin('author.profilePicture', 'picture')
            ->where('message.thread = :thread')
            ->orderBy('message.creationDatetime', 'DESC')
            ->addOrderBy('message.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getResult();
    }

    public function countForThread(MessageThread $thread): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.thread = :thread')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many messages in each of this user's threads they have not read yet, keyed by thread id.
     *
     * One grouped query for the whole inbox rather than one per thread: the message list is already
     * hydrated with five joins by MessageThreadMetaRepository::findByUserAndNotDeleted(), and adding
     * a count per row on top of that is how an inbox starts costing dozens of queries.
     *
     * A thread the user has read entirely is absent from the result, not present with a zero, so
     * callers read it with `?? 0`.
     *
     * @return array<string, int>
     */
    public function countUnreadByThreadForUser(User $user): array
    {
        /** @var list<array{thread_id: string, unread_count: int|string}> $rows */
        $rows = $this->createQueryBuilder('message')
            ->select('IDENTITY(message.thread) AS thread_id, COUNT(message.id) AS unread_count')
            ->join(
                MessageThreadMeta::class,
                'meta',
                Join::WITH,
                'meta.thread = message.thread AND meta.user = :user'
            )
            // Own messages never count: you have read what you wrote.
            ->where('message.author != :user')
            ->andWhere('(meta.lastReadDatetime IS NULL OR message.creationDatetime > meta.lastReadDatetime)')
            ->groupBy('message.thread')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['thread_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }

    /**
     * Every message this user has not read, across every thread they have not deleted.
     *
     * This is the navbar badge. It used to count *threads* with an unread flag and to ignore
     * isDeleted, which made it disagree with the inbox it sits above on both counts (#954).
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->join(
                MessageThreadMeta::class,
                'meta',
                Join::WITH,
                'meta.thread = message.thread AND meta.user = :user'
            )
            ->where('message.author != :user')
            ->andWhere('meta.isDeleted = false')
            ->andWhere('(meta.lastReadDatetime IS NULL OR message.creationDatetime > meta.lastReadDatetime)')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The same count as countUnreadByThreadForUser(), for one thread.
     *
     * Not that method with a filter: buildItem() is reached twice per "open a thread" click, once
     * from the PATCH provider and once from its processor, and running a GROUP BY over every message
     * the user has to learn a number that is about to be zero is the wrong shape for a click.
     */
    public function countUnreadForThread(MessageThreadMeta $meta): int
    {
        $queryBuilder = $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.thread = :thread')
            ->andWhere('message.author != :user')
            ->setParameter('thread', $meta->thread)
            ->setParameter('user', $meta->user);

        // Nothing read means everything counts, so there is simply no bound to add.
        if ($meta->lastReadDatetime !== null) {
            $queryBuilder->andWhere('message.creationDatetime > :lastRead')
                ->setParameter('lastRead', $meta->lastReadDatetime);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Count messages sent within a date range.
     */
    public function countMessagesSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.creationDatetime >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total messages.
     */
    public function countTotalMessages(): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count messages grouped by date within a range.
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countMessagesByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM message
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

    /**
     * Get top messagers within a date range.
     *
     * @return array<int, array{user_id: string, username: string, message_count: int, account_age_days: int, creation_datetime: \DateTimeInterface}>
     */
    public function findTopMessagers(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        /** @var array<int, array{user_id: string, username: string, message_count: string, creation_datetime: \DateTimeInterface}> $results */
        $results = $this->createQueryBuilder('message')
            ->select('IDENTITY(message.author) as user_id, u.username, COUNT(message.id) as message_count, u.creationDatetime as creation_datetime')
            ->join('message.author', 'u')
            ->where('message.creationDatetime >= :from')
            ->andWhere('message.creationDatetime < :to')
            ->groupBy('message.author, u.username, u.creationDatetime')
            ->orderBy('message_count', 'DESC')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $now = new \DateTimeImmutable();

        return array_map(function (array $row) use ($now): array {
            $creationDate = \DateTimeImmutable::createFromInterface($row['creation_datetime']);
            $diff = $now->diff($creationDate);

            return [
                'user_id' => $row['user_id'],
                'username' => $row['username'],
                'message_count' => (int) $row['message_count'],
                'account_age_days' => $diff->days,
                'creation_datetime' => $creationDate,
            ];
        }, $results);
    }
}

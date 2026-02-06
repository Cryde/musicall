<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            fn (array $row) => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
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
                'account_age_days' => $diff->days !== false ? $diff->days : 0,
                'creation_datetime' => $creationDate,
            ];
        }, $results);
    }
}

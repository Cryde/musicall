<?php declare(strict_types=1);

namespace App\Repository\Notification;

use App\Entity\Notification\Notification;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnread(User $recipient): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :recipient')
            ->andWhere('n.readDatetime IS NULL')
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Notification[]
     */
    public function findForRecipient(User $recipient, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('n.creationDatetime', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countForRecipient(User $recipient): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByIdAndRecipient(string $id, User $recipient): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('id', $id)
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markAllReadForRecipient(User $recipient, DateTimeImmutable $readAt): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.readDatetime', ':readAt')
            ->where('n.recipient = :recipient')
            ->andWhere('n.readDatetime IS NULL')
            ->setParameter('readAt', $readAt)
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->execute();
    }

    public function deleteReadOlderThan(DateTimeImmutable $cutoff): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(Notification::class, 'n')
            ->where('n.readDatetime IS NOT NULL')
            ->andWhere('n.readDatetime < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}

<?php declare(strict_types=1);

namespace App\Repository\Message;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\NonUniqueResultException;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageThread>
 */
class MessageThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageThread::class);
    }

    /**
     * @param mixed|User[] ...$participants
     *
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findByParticipants(...$participants): mixed
    {
        return $this->createQueryBuilder('message_thread')
            ->join(
                'message_thread.messageParticipants', 'message_participants_with',
                Join::WITH, 'message_participants_with.participant IN (:participants)')
            ->leftJoin('message_thread.messageParticipants', 'message_participants_without',
                Join::WITH, 'message_participants_without.participant NOT IN (:participants)')
            ->where('message_participants_without.id IS NULL')
            ->groupBy('message_thread.id')
            ->having('count(message_participants_with) = :number_participant')
            ->setParameter('participants', $participants)
            ->setParameter('number_participant', count($participants))
            ->getQuery()
            ->getOneOrNullResult();
    }
}

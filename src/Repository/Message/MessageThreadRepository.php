<?php

namespace App\Repository\Message;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MessageThread|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageThread|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageThread[]    findAll()
 * @method MessageThread[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByParticipants(...$participants)
    {
        return $this->createQueryBuilder('message_thread')
            ->join(
                'message_thread.messageParticipants', 'message_participants_with',
                Expr\Join::WITH, 'message_participants_with.participant IN (:participants)')
            ->leftJoin('message_thread.messageParticipants', 'message_participants_without',
                Expr\Join::WITH, 'message_participants_without.participant NOT IN (:participants)')
            ->where('message_participants_without.id IS NULL')
            ->groupBy('message_thread.id')
            ->having('count(message_participants_with) = :number_participant')
            ->setParameter('participants', $participants)
            ->setParameter('number_participant', count($participants))
            ->getQuery()
            ->getOneOrNullResult();
    }
}

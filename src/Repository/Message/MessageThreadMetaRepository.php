<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MessageThreadMeta|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageThreadMeta|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageThreadMeta[]    findAll()
 * @method MessageThreadMeta[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageThreadMetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageThreadMeta::class);
    }

    public function findByUserAndNotDeleted(User $user): mixed
    {
        return $this->createQueryBuilder('message_thread_meta')
            ->select('message_thread_meta, thread, last_message, author, message_participants, participant')
            ->join('message_thread_meta.thread', 'thread')
            ->join('thread.messageParticipants', 'message_participants')
            ->join('message_participants.participant', 'participant')
            ->join('thread.lastMessage', 'last_message')
            ->join('last_message.author', 'author')
            ->where('message_thread_meta.user = :user')
            ->andWhere('message_thread_meta.isDeleted = 0')
            ->orderBy('last_message.creationDatetime', 'DESC')
            ->addOrderBy('participant.username', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}

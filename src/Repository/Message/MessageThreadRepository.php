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

    /**
     * Deletes a Band Space's channels and everything in them, for app:band-space:purge.
     *
     * The order is the whole method. Every foreign key in the message domain is RESTRICT, and
     * message_thread.last_message_id -> message.id -> message.thread_id -> message_thread.id is a
     * cycle, so the pointer has to be nulled before the messages can go and the messages before the
     * thread. Without this, the ON DELETE CASCADE from band_space cannot delete a channel that holds
     * a single message, and the purge fails on the whole space.
     *
     * A bulk delete, so no lifecycle event fires: anything a message ever gains that lives outside
     * the database, an attachment for instance, has to be removed by the caller. isDeleted on the
     * read-state rows is ignored on purpose, since this is a hard purge and not somebody tidying
     * their own inbox.
     */
    public function deleteByBandSpace(string $bandSpaceId): void
    {
        $entityManager = $this->getEntityManager();

        $statements = [
            'UPDATE App\Entity\Message\MessageThread thread SET thread.lastMessage = NULL WHERE thread.bandSpace = :band_space',
            'DELETE FROM App\Entity\Message\MessageThreadMeta meta WHERE meta.thread IN (SELECT owned.id FROM App\Entity\Message\MessageThread owned WHERE owned.bandSpace = :band_space)',
            'DELETE FROM App\Entity\Message\Message message WHERE message.thread IN (SELECT owned.id FROM App\Entity\Message\MessageThread owned WHERE owned.bandSpace = :band_space)',
            'DELETE FROM App\Entity\Message\MessageParticipant participant WHERE participant.thread IN (SELECT owned.id FROM App\Entity\Message\MessageThread owned WHERE owned.bandSpace = :band_space)',
            // The threads themselves go last, and by bandSpace rather than by a subquery over their own
            // table: a DQL DELETE on a table with no inheritance is emitted verbatim, so that subquery
            // would reach MariaDB as error 1093.
            'DELETE FROM App\Entity\Message\MessageThread thread WHERE thread.bandSpace = :band_space',
        ];

        foreach ($statements as $dql) {
            $entityManager->createQuery($dql)->setParameter('band_space', $bandSpaceId)->execute();
        }
    }
}

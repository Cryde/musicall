<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageThreadMeta>
 */
class MessageThreadMetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageThreadMeta::class);
    }

    /**
     * Every read-state row for one thread, keyed by the id of the user it belongs to.
     *
     * One query instead of the `findOneBy` per participant the send path used to run (#957). Invisible
     * at two participants and linear in channel size, which is the point: a Band Space channel is the
     * caller this exists for.
     *
     * Deliberately unfiltered on `isDeleted`, unlike findByUserAndNotDeleted() below. That flag is a
     * user hiding the thread from their own inbox, not a row to be replaced: hiding it from the send
     * path would mean finding no row, inserting a second one, and hitting `UNIQUE (thread_id, user_id)`.
     * Reuse leaves the row hidden, which is the conservative answer rather than the obviously right
     * one, and academic either way since nothing in the application sets the flag today.
     *
     * @return array<string, MessageThreadMeta>
     */
    public function findByThreadIndexedByUserId(MessageThread $thread): array
    {
        $metas = $this->createQueryBuilder('message_thread_meta')
            ->where('message_thread_meta.thread = :thread')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($metas as $meta) {
            $indexed[(string) $meta->user->id] = $meta;
        }

        return $indexed;
    }

    /**
     * Scoped to the owner, so there is no way to load somebody else's row through this method.
     */
    public function findOneByIdAndUser(string $id, User $user): ?MessageThreadMeta
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
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
            // Direct messages only. A channel is kept out today by the inner join on participants,
            // which it has none of, but #960 gives its members read-state rows and #994 is where
            // showing them in this inbox becomes a decision rather than an accident.
            ->andWhere('thread.bandSpace IS NULL')
            ->orderBy('last_message.creationDatetime', 'DESC')
            ->addOrderBy('participant.username', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}

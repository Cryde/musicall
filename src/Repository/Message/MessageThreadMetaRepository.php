<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\BandSpace\BandSpace;
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
     * Moves this member's read position in the space's channels to now, creating the row if they have
     * none.
     *
     * Two callers, one rule: your read position starts when you join, and starts again when you
     * rejoin. Opening the tab uses it to clear the badge, and the invitation paths use it because a
     * rejoining member reuses their old membership row, and its creation date is their *first* join,
     * which the unread count floors at.
     *
     * It has to insert, not just update, and that is the whole reason this is raw SQL: DQL has no
     * INSERT, and a member whose first stint saw no messages at all never got a row, so an update
     * would silently do nothing for exactly the person who needs it most. `ON DUPLICATE KEY UPDATE`
     * against `UNIQUE (thread_id, user_id)` also makes this safe against a send creating the same row
     * concurrently, which a bare insert outside the thread lock would not be.
     *
     * UUID() is MariaDB's version 1 where the application mints version 4. Nothing reads the version,
     * and the same trade was already made for the channel backfill in #959.
     */
    public function markChannelsReadForUser(BandSpace $bandSpace, User $user): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO message_thread_meta
                    (id, thread_id, user_id, creation_datetime, is_deleted, pending_notification_sent, last_read_datetime)
                SELECT UUID(), thread.id, :user, :now, 0, 0, :now
                FROM message_thread thread
                WHERE thread.band_space_id = :band_space
                ON DUPLICATE KEY UPDATE last_read_datetime = :now
                SQL,
            [
                'user' => (string) $user->id,
                'band_space' => (string) $bandSpace->id,
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
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

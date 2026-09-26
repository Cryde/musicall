<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageReaction;
use App\Entity\User;
use App\Enum\Message\MessageReactionEmoji;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageReaction>
 */
class MessageReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageReaction::class);
    }

    /**
     * Every reaction a page of messages carries, counted, in one grouped query.
     *
     * One query for the whole page and never one per message: a fifty message page counting its own
     * reactions is the same N+1 the projection in MessageRepository::findForThread() exists to avoid.
     * Nothing is hydrated either, for the same reason: reading the reacting user as an entity would
     * drag the three profile tables of #730 along with it, and the aggregate only needs to know
     * whether one of the rows is the viewer's.
     *
     * The viewer flag is folded into the same GROUP BY rather than fetched separately, so adding
     * « have I reacted » costs no extra round trip.
     *
     * A message with no reactions is absent from the result, not present with an empty list, so
     * callers read it with `?? []`.
     *
     * @param string[] $messageIds
     *
     * @return array<string, array<string, array{count: int, hasReacted: bool}>> message id => (emoji slug => tally)
     */
    public function findAggregatedByMessageIds(array $messageIds, User $viewer): array
    {
        if ($messageIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('reaction')
            ->select(
                'IDENTITY(reaction.message) AS messageId',
                'reaction.emoji AS emoji',
                'COUNT(reaction.id) AS total',
                'SUM(CASE WHEN reaction.user = :viewer THEN 1 ELSE 0 END) AS viewerTotal',
            )
            ->where('reaction.message IN (:messageIds)')
            ->groupBy('reaction.message')
            ->addGroupBy('reaction.emoji')
            ->setParameter('messageIds', $messageIds)
            ->setParameter('viewer', $viewer)
            ->getQuery()
            ->getArrayResult();

        $byMessage = [];
        foreach ($rows as $row) {
            // The enum comes back built, not as its backing value: Doctrine applies enumType to a
            // scalar projection too.
            $byMessage[(string) $row['messageId']][$row['emoji']->value] = [
                'count' => (int) $row['total'],
                'hasReacted' => ((int) $row['viewerTotal']) > 0,
            ];
        }

        return $byMessage;
    }

    /**
     * Leaves this member's reaction on a message, or does nothing if they already left that one.
     *
     * Raw SQL for the reason MessageThreadMetaRepository::markChannelsReadForUser() gives: DQL has no
     * INSERT. Reading first and persisting second would be correct for two taps in sequence and wrong
     * for two at once, since both would find no row, both would insert, and the loser would get a 500
     * off the unique index instead of the no-op this endpoint promises. `ON DUPLICATE KEY UPDATE`
     * against `UNIQUE (message_id, user_id, emoji)` lets the database settle it in one statement.
     *
     * Nothing is lost by going round the ORM here: a reaction has no lifecycle listener, and the row
     * is read back through the aggregate rather than from the identity map.
     *
     * UUID() is MariaDB's version 1 where the ORM mints version 4. Nothing reads the version, and the
     * same trade was already made for the channel backfill in #959.
     *
     * Answers whether a row was written: the duplicate path changes nothing and reports no affected
     * row, which is how a repeated tap stays silent on the live channel (#1056).
     */
    public function add(Message $message, User $user, MessageReactionEmoji $emoji): bool
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO message_reaction (id, message_id, user_id, emoji)
                VALUES (UUID(), :message, :user, :emoji)
                ON DUPLICATE KEY UPDATE emoji = emoji
                SQL,
            [
                'message' => (string) $message->id,
                'user' => (string) $user->id,
                'emoji' => $emoji->value,
            ],
        ) > 0;
    }
}

<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\MessageMention;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageMention>
 */
class MessageMentionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageMention::class);
    }

    /**
     * The names to print for a page of messages, in one query.
     *
     * A **scalar projection**, not hydrated entities, and that is the point: hydrating a `User` drags
     * three `LEFT JOIN`s of its own along (#730), and #986 measured what that costs when it happens
     * per row. Two columns are all the renderer needs.
     *
     * The username is read live rather than frozen at send time, so a rename shows through on old
     * messages, the same way it does everywhere else the mention format is used. A deleted account is
     * the one exception: DeleteAccountProcedure rewrites the handle to `deleted_<uuid>`, so the name
     * is substituted here exactly as ChatMessageBuilder already does for an author.
     *
     * @param string[] $messageIds
     *
     * @return array<string, array<string, string>> message id => (user id => username)
     */
    public function findUsernamesByMessageIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('mention')
            ->select(
                'IDENTITY(mention.message) AS messageId',
                'user.id AS userId',
                'user.username AS username',
                'user.deletionDatetime AS deletionDatetime',
            )
            ->innerJoin('mention.mentionedUser', 'user')
            ->where('mention.message IN (:messageIds)')
            ->setParameter('messageIds', $messageIds)
            ->getQuery()
            ->getArrayResult();

        $byMessage = [];
        foreach ($rows as $row) {
            // Lower-cased, because the mention format accepts a uuid in either case and the renderer
            // lower-cases the token before looking it up.
            $byMessage[(string) $row['messageId']][mb_strtolower((string) $row['userId'])] =
                $row['deletionDatetime'] === null ? (string) $row['username'] : User::DELETED_DISPLAY_NAME;
        }

        return $byMessage;
    }
}

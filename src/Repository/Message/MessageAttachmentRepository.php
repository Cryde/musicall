<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Message\Message;
use App\Entity\Message\MessageAttachment;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Service\Message\MessageAttachmentTargets;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageAttachment>
 */
class MessageAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageAttachment::class);
    }

    /**
     * The attachment rows of a whole page of messages, in one query.
     *
     * Ordered by target kind then id rather than by creation: a message's rows are written in a
     * single flush, so their second-granular datetime cannot separate them, and a payload nobody can
     * predict is a payload no test can assert. Grouping by kind is also how the command palette
     * already presents the same seven kinds.
     *
     * @param string[] $messageIds
     *
     * @return array<string, list<array{type: BandSpaceSearchResultType, targetId: string, label: string}>>
     */
    public function findByMessageIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('attachment')
            ->select(
                'IDENTITY(attachment.message) AS messageId',
                'attachment.targetType AS targetType',
                'attachment.targetId AS targetId',
                'attachment.label AS label',
            )
            ->where('attachment.message IN (:messageIds)')
            ->orderBy('attachment.targetType', 'ASC')
            ->addOrderBy('attachment.targetId', 'ASC')
            ->setParameter('messageIds', $messageIds)
            ->getQuery()
            ->getArrayResult();

        $byMessage = [];
        foreach ($rows as $row) {
            $byMessage[(string) $row['messageId']][] = [
                'type' => $row['targetType'],
                'targetId' => (string) $row['targetId'],
                'label' => (string) $row['label'],
            ];
        }

        return $byMessage;
    }

    /**
     * How many Band Space objects this message already points at, for the cap (#979).
     */
    public function countByMessage(Message $message): int
    {
        return (int) $this->createQueryBuilder('attachment')
            ->select('COUNT(attachment.id)')
            ->where('attachment.message = :message')
            ->setParameter('message', $message->id)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The oldest message of this space pointing at each of these targets, in one query whatever their
     * number, read through `idx_message_attachment_target`: the index the table carries for exactly
     * this direction.
     *
     * Oldest, because for a task created from a message (#979) that is provably the message it came
     * from: the row on that message is written the moment the task exists, so nothing older can point
     * at it. For a task created on the board and referenced later it is simply the first time the band
     * talked about it, which is why the payload calls it linked rather than source.
     *
     * A tombstoned message has no attachment rows left, so a deleted conversation never answers here.
     *
     * @param string[] $targetIds
     *
     * @return array<string, string> lower-cased target id => message id
     */
    public function findOldestLinkedMessageIds(
        BandSpaceSearchResultType $type,
        array $targetIds,
        string $bandSpaceId,
    ): array {
        if ($targetIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('attachment')
            ->select('attachment.targetId AS targetId', 'message.id AS messageId')
            ->join('attachment.message', 'message')
            ->join('message.thread', 'thread')
            ->where('attachment.targetType = :type')
            ->andWhere('attachment.targetId IN (:targetIds)')
            ->andWhere('thread.bandSpace = :bandSpace')
            ->orderBy('message.creationDatetime', 'ASC')
            ->addOrderBy('message.id', 'ASC')
            ->setParameter('type', $type)
            ->setParameter('targetIds', $targetIds)
            ->setParameter('bandSpace', $bandSpaceId)
            ->getQuery()
            ->getArrayResult();

        $oldestByTarget = [];
        foreach ($rows as $row) {
            // Ascending, so the first row seen for a target is the one kept. The id only breaks a tie
            // the second-granular datetime cannot, and it is a stable pick rather than a chronology:
            // Message.id is uuid4 and carries no order. Two messages naming the same task within the
            // same second are indistinguishable, so this answers with the same one every time instead
            // of with whatever the database felt like returning.
            $oldestByTarget[mb_strtolower((string) $row['targetId'])] ??= (string) $row['messageId'];
        }

        return $oldestByTarget;
    }

    /**
     * Which of these targets still exist in this space, out of the ids given, in one query whatever
     * their number.
     *
     * This is the whole of what `is_available` answers, and it is all a card needs: the label it
     * shows is the one snapshotted when it was attached and is never re-read. Reading a title live
     * would hand every reader of the channel a value its owner disclosed exactly once, which a
     * personal finance entry makes concrete and which #785 would extend to every kind.
     *
     * An archived task, song, setlist or file still answers here. Archiving is a trash state the band
     * can undo, so the card keeps its link; only a row that has really gone loses it.
     *
     * @param string[] $targetIds
     *
     * @return array<string, true> lower-cased target id => it is still there
     */
    public function findExistingTargetIds(BandSpaceSearchResultType $type, array $targetIds, string $bandSpaceId): array
    {
        if ($targetIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->createQuery($this->targetDql($type, 'target.id AS id'))
            ->setParameter('ids', $targetIds)
            ->setParameter('bandSpace', $bandSpaceId)
            ->getArrayResult();

        $existing = [];
        foreach ($rows as $row) {
            $existing[mb_strtolower((string) $row['id'])] = true;
        }

        return $existing;
    }

    /**
     * The current titles of one kind of target, for the member about to attach them: this is the
     * value that gets snapshotted onto the row.
     *
     * A projection rather than entities, for the same reason the message list is one: a title is all
     * this needs, and hydrating an aggregate to read one column is how a chat page gets expensive.
     *
     * `$viewer` carries the one visibility rule a band space has today, a personal finance entry
     * belonging to the member it names, written exactly as FinanceEntryRepository::applyVisibleTo()
     * writes it. Without it a member could reference, and so read the title of, an entry the command
     * palette would never have offered them. Should per-module permissions ever land (#785), this is
     * where the rest of them go.
     *
     * @param string[] $targetIds
     *
     * @return array<string, string> lower-cased target id => current title
     */
    public function findTargetTitles(
        BandSpaceSearchResultType $type,
        array $targetIds,
        string $bandSpaceId,
        BandSpaceMembership $viewer,
    ): array {
        if ($targetIds === []) {
            return [];
        }

        $dql = $this->targetDql($type, sprintf(
            'target.id AS id, target.%s AS title',
            MessageAttachmentTargets::TITLE_PROPERTY_BY_TYPE[$type->value],
        ));

        $parameters = ['ids' => $targetIds, 'bandSpace' => $bandSpaceId];
        if ($type === BandSpaceSearchResultType::Finance) {
            $dql .= ' AND (target.scope = :bandScope OR target.member = :viewer)';
            $parameters['bandScope'] = FinanceEntryScope::Band;
            $parameters['viewer'] = $viewer;
        }

        $query = $this->getEntityManager()->createQuery($dql);
        foreach ($parameters as $name => $parameterValue) {
            $query->setParameter($name, $parameterValue);
        }

        $titles = [];
        foreach ($query->getArrayResult() as $row) {
            $titles[mb_strtolower((string) $row['id'])] = (string) $row['title'];
        }

        return $titles;
    }

    /**
     * The skeleton both lookups share, up to and including the id filter. Expects `:ids` and
     * `:bandSpace`.
     */
    private function targetDql(BandSpaceSearchResultType $type, string $select): string
    {
        // A finance entry is the one kind that reaches its space through another table.
        $isFinance = $type === BandSpaceSearchResultType::Finance;

        return sprintf(
            'SELECT %s FROM %s target %s WHERE %s AND target.id IN (:ids)',
            $select,
            MessageAttachmentTargets::ENTITY_BY_TYPE[$type->value],
            $isFinance ? 'JOIN target.category category' : '',
            $isFinance ? 'category.bandSpace = :bandSpace' : 'target.bandSpace = :bandSpace',
        );
    }

    /**
     * Drops what a message pointed at, when that message is tombstoned (#967).
     *
     * A bulk delete, so no lifecycle event fires and nothing cascades: these rows reference no file
     * and carry only a snapshotted label, so there is nothing outside the database to clean up.
     */
    public function deleteByMessage(Message $message): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Message\MessageAttachment attachment WHERE attachment.message = :message')
            ->setParameter('message', $message->id)
            ->execute();
    }
}

<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * One page of a thread's messages, as scalars.
     *
     * Nothing is hydrated, and that is the point. `User` carries three inverse one-to-one profile
     * associations that Doctrine cannot make lazy (#730), so hydrating one author costs four selects
     * and a page of fifty distinct authors costs two hundred. The author is reduced to the fields the
     * renderer needs, and the profile picture to its `imageName`, which is all
     * UserProfilePictureUrlBuilder::buildFromImageName() needs to rebuild the URL. Same shape as
     * MusicianAnnounceRepository::findAuthorsDataForAnnounces(), for the same reason.
     *
     * Newest first, with `id` as the tiebreak #955 made mandatory: `creation_datetime` is second
     * granular, ties are ordinary, and SQL promises nothing about their order, so two page requests
     * planned differently could put a tied row on both sides of a boundary. DESC on both columns lets
     * the planner read `idx_message_thread_creation` backwards, which is physically `(thread_id,
     * creation_datetime, id)`, instead of sorting: measured on a real thread, though the plan depends
     * on the author join staying an eq_ref, so it is a good default rather than a guarantee.
     *
     * A deleted message stays in the page, content emptied and `deletionDatetime` set. Filtering the
     * tombstones out would leave holes in a conversation and put the page size out of step with the
     * count below (#967).
     *
     * @return array<int, array{id: string, content: string, creationDatetime: \DateTimeInterface, updateDatetime: ?\DateTimeImmutable, deletionDatetime: ?\DateTimeImmutable, imageFileId: ?string, voiceNoteFileId: ?string, voiceNoteDurationSeconds: ?int, authorId: string, authorUsername: string, authorDeletionDatetime: ?\DateTimeImmutable, authorProfilePictureName: ?string, pinnedDatetime: ?\DateTimeImmutable, pinnedByUsername: ?string, pinnedByDeletionDatetime: ?\DateTimeImmutable}>
     */
    public function findForThread(MessageThread $thread, int $limit, int $offset): array
    {
        return $this->projectionForThread($thread)
            ->orderBy('message.creationDatetime', 'DESC')
            ->addOrderBy('message.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * The channel's pinned messages, newest pin first, in the same shape as a page of the list.
     *
     * Its own collection rather than a filter on the list, because the point of a pin is that the
     * message is far up the history and therefore almost never on the page the pane has loaded (#969).
     * Unbounded on purpose: ChatMessagePinProcessor caps a channel at ten, so there is no page to turn.
     *
     * @return array<int, array{id: string, content: string, creationDatetime: \DateTimeInterface, updateDatetime: ?\DateTimeImmutable, deletionDatetime: ?\DateTimeImmutable, imageFileId: ?string, voiceNoteFileId: ?string, voiceNoteDurationSeconds: ?int, authorId: string, authorUsername: string, authorDeletionDatetime: ?\DateTimeImmutable, authorProfilePictureName: ?string, pinnedDatetime: ?\DateTimeImmutable, pinnedByUsername: ?string, pinnedByDeletionDatetime: ?\DateTimeImmutable}>
     */
    public function findPinnedForThread(MessageThread $thread): array
    {
        return $this->projectionForThread($thread)
            ->andWhere('message.pinnedDatetime IS NOT NULL')
            ->orderBy('message.pinnedDatetime', 'DESC')
            ->addOrderBy('message.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The select and the joins both projections share, so a column added for the list cannot go
     * missing from the pinned bar, which renders the same resource.
     */
    private function projectionForThread(MessageThread $thread): QueryBuilder
    {
        return $this->createQueryBuilder('message')
            ->select(
                'message.id AS id',
                'message.content AS content',
                'message.creationDatetime AS creationDatetime',
                'message.updateDatetime AS updateDatetime',
                'message.deletionDatetime AS deletionDatetime',
                'message.imageFileId AS imageFileId',
                'message.voiceNoteFileId AS voiceNoteFileId',
                'message.voiceNoteDurationSeconds AS voiceNoteDurationSeconds',
                'author.id AS authorId',
                'author.username AS authorUsername',
                'author.deletionDatetime AS authorDeletionDatetime',
                'picture.imageName AS authorProfilePictureName',
                'message.pinnedDatetime AS pinnedDatetime',
                // Joined here rather than looked up per message: hydrating the pinner would drag the
                // three profile tables along (#730) once per row, which is the very cost this
                // projection exists to avoid.
                'pinner.username AS pinnedByUsername',
                'pinner.deletionDatetime AS pinnedByDeletionDatetime',
            )
            ->join('message.author', 'author')
            ->leftJoin('author.profilePicture', 'picture')
            ->leftJoin('message.pinnedBy', 'pinner')
            ->where('message.thread = :thread')
            ->setParameter('thread', $thread);
    }

    /**
     * One message of a band's channel, scoped to the space so an id from another band's conversation
     * is simply not found rather than acted on.
     */
    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?Message
    {
        return $this->createQueryBuilder('message')
            ->join('message.thread', 'thread')
            ->where('message.id = :id')
            ->andWhere('thread.bandSpace = :band_space')
            ->setParameter('id', $id)
            ->setParameter('band_space', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countForThread(MessageThread $thread): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.thread = :thread')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPinnedForThread(MessageThread $thread): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.thread = :thread')
            ->andWhere('message.pinnedDatetime IS NOT NULL')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * How many messages in each of this user's threads they have not read yet, keyed by thread id.
     *
     * One grouped query for the whole inbox rather than one per thread: the message list is already
     * hydrated with five joins by MessageThreadMetaRepository::findByUserAndNotDeleted(), and adding
     * a count per row on top of that is how an inbox starts costing dozens of queries.
     *
     * A thread the user has read entirely is absent from the result, not present with a zero, so
     * callers read it with `?? 0`.
     *
     * Since #994 this covers Band Space channels too, and it has to carry their rule to do it. See
     * channelMembershipRule(): counting a channel the way a direct message is counted tells somebody
     * who joined this morning that the whole history is unread, keeps counting for somebody who has
     * left, and disagrees with the sidebar badge for the same band.
     *
     * @return array<string, int>
     */
    public function countUnreadByThreadForUser(User $user): array
    {
        /** @var list<array{thread_id: string, unread_count: int|string}> $rows */
        $rows = $this->createQueryBuilder('message')
            ->select('IDENTITY(message.thread) AS thread_id, COUNT(message.id) AS unread_count')
            ->join('message.thread', 'thread')
            ->join(
                MessageThreadMeta::class,
                'meta',
                Join::WITH,
                'meta.thread = message.thread AND meta.user = :user'
            )
            ->leftJoin(
                BandSpaceMembership::class,
                'membership',
                Join::WITH,
                $this->channelMembershipJoin()
            )
            // Own messages never count: you have read what you wrote.
            ->where('message.author != :user')
            ->andWhere('(meta.lastReadDatetime IS NULL OR message.creationDatetime > meta.lastReadDatetime)')
            ->andWhere($this->channelMembershipRule())
            ->groupBy('message.thread')
            ->setParameter('user', $user)
            ->setParameter('active', MembershipStatus::Active)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['thread_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }

    /**
     * Every message this user has not read, across every direct message thread they have not deleted.
     *
     * This is the navbar badge. It used to count *threads* with an unread flag and to ignore
     * isDeleted, which made it disagree with the inbox it sits above on both counts (#954).
     *
     * Band Space channels used to be excluded, because the inbox this badge sits above listed only
     * direct messages, so counting them showed a number clicking through could neither explain nor
     * clear (#961). #994 lists channels in that inbox and lets them be opened from it, which removes
     * the whole of that reason, so they are counted again and carry the same rule as everywhere else,
     * see channelMembershipRule(). A band's messages therefore show here and on its sidebar entry,
     * which are two views of one `lastReadDatetime` rather than two separate things.
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->join('message.thread', 'thread')
            ->join(
                MessageThreadMeta::class,
                'meta',
                Join::WITH,
                'meta.thread = message.thread AND meta.user = :user'
            )
            ->leftJoin(
                BandSpaceMembership::class,
                'membership',
                Join::WITH,
                $this->channelMembershipJoin()
            )
            ->where('message.author != :user')
            ->andWhere('meta.isDeleted = false')
            ->andWhere('(meta.lastReadDatetime IS NULL OR message.creationDatetime > meta.lastReadDatetime)')
            ->andWhere($this->channelMembershipRule())
            ->setParameter('user', $user)
            ->setParameter('active', MembershipStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * What a Band Space channel has to satisfy to be counted, for a query that also counts direct
     * messages. Expects a `thread` alias and a **left** joined `membership` one.
     *
     * A direct message has no band space, so no membership row matches and the first branch lets it
     * through untouched. A channel must have an active membership, which is what keeps a former
     * member's surviving read-state row out without deleting anything (#948 concern 3), and its
     * messages must postdate that membership, because a member's row is created lazily by the first
     * message after they join, with no read position: without the floor, somebody who joined this
     * morning is told the entire history is unread. Flooring at the moment they joined needs no write
     * at join time, which keeps it clear of the duplicate-key hazard
     * MessageSenderProcedure::findOrCreateMetaFor() warns about.
     *
     * Inner joining instead would be simpler and wrong: it would drop every direct message.
     */
    private function channelMembershipRule(): string
    {
        return 'thread.bandSpace IS NULL'
            . ' OR (membership.id IS NOT NULL AND message.creationDatetime > membership.creationDatetime)';
    }

    /**
     * Which membership row the rule above is talking about. Shared with it rather than written at
     * each join, because the two have to say the same thing: widen one and the other silently stops
     * meaning what its name says.
     */
    private function channelMembershipJoin(): string
    {
        return 'membership.bandSpace = thread.bandSpace AND membership.user = :user'
            . ' AND membership.status = :active';
    }

    /**
     * Unread in each Band Space chat the user is an active member of, keyed by band space id.
     *
     * This is the sidebar badge. Still its own number after #994 put channels in the inbox and back
     * into the envelope: this one is per band, which is what the sidebar shows, while the envelope is
     * a single total. Both read the same `lastReadDatetime`, so they cannot disagree.
     *
     * The membership join is doing two jobs. It filters to **active** members, which is what keeps a
     * former member's surviving read-state row out of the count without deleting anything (#948
     * concern 3). And its creationDatetime is the floor: a member's row is created lazily by the first
     * message sent after they join, with no read position, so without the floor somebody who joined
     * this morning would be told the whole history is unread. Flooring at the moment they joined needs
     * no write at join time, which is what keeps this clear of the duplicate-key hazard
     * MessageSenderProcedure::findOrCreateMetaFor() warns about.
     *
     * A space with nothing unread is absent rather than present with a zero, like the sibling method,
     * so callers read it with `?? 0`.
     *
     * @return array<string, int>
     */
    public function countUnreadChannelsForUser(User $user): array
    {
        /** @var list<array{band_space_id: string, unread_count: int|string}> $rows */
        $rows = $this->createQueryBuilder('message')
            ->select('IDENTITY(thread.bandSpace) AS band_space_id, COUNT(message.id) AS unread_count')
            ->join('message.thread', 'thread')
            ->join(
                MessageThreadMeta::class,
                'meta',
                Join::WITH,
                'meta.thread = message.thread AND meta.user = :user'
            )
            ->join(
                BandSpaceMembership::class,
                'membership',
                Join::WITH,
                $this->channelMembershipJoin()
            )
            ->where('thread.bandSpace IS NOT NULL')
            // Own messages never count: you have read what you wrote.
            ->andWhere('message.author != :user')
            ->andWhere('(meta.lastReadDatetime IS NULL OR message.creationDatetime > meta.lastReadDatetime)')
            ->andWhere('message.creationDatetime > membership.creationDatetime')
            ->groupBy('thread.bandSpace')
            ->setParameter('user', $user)
            ->setParameter('active', MembershipStatus::Active)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['band_space_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }

    /**
     * The same count as countUnreadByThreadForUser(), for one thread.
     *
     * Not that method with a filter: buildItem() is reached twice per "open a thread" click, once
     * from the PATCH provider and once from its processor, and running a GROUP BY over every message
     * the user has to learn a number that is about to be zero is the wrong shape for a click.
     */
    public function countUnreadForThread(MessageThreadMeta $meta): int
    {
        $queryBuilder = $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.thread = :thread')
            ->andWhere('message.author != :user')
            ->setParameter('thread', $meta->thread)
            ->setParameter('user', $meta->user);

        // Nothing read means everything counts, so there is simply no bound to add.
        if ($meta->lastReadDatetime !== null) {
            $queryBuilder->andWhere('message.creationDatetime > :lastRead')
                ->setParameter('lastRead', $meta->lastReadDatetime);
        }

        // A channel carries the same rule as the two list counts, so one row cannot report a
        // different number depending on which of the three was asked. Inner joined here rather than
        // left, because this method is only ever called about one known thread: a former member gets
        // no row, and therefore zero, instead of the history of a band they have left.
        if ($meta->thread->bandSpace instanceof BandSpace) {
            $queryBuilder
                ->join('message.thread', 'thread')
                ->join(BandSpaceMembership::class, 'membership', Join::WITH, $this->channelMembershipJoin())
                ->andWhere('message.creationDatetime > membership.creationDatetime')
                ->setParameter('active', MembershipStatus::Active);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Whether marking the space's channels read would change anybody's « Vu par » (#977).
     *
     * The read receipt rule, not the unread badge's: no membership floor, since a receipt has none,
     * and tombstones left out, since a deleted message reports no reader. Asked before the position
     * moves, because afterwards it is `now` and the answer is gone.
     */
    public function hasChannelMessageUnreadBy(BandSpace $bandSpace, User $reader): bool
    {
        $found = $this->createQueryBuilder('message')
            ->select('message.id')
            ->join('message.thread', 'thread')
            ->leftJoin(
                MessageThreadMeta::class,
                'meta',
                Join::WITH,
                'meta.thread = message.thread AND meta.user = :reader'
            )
            ->where('thread.bandSpace = :band_space')
            ->andWhere('message.author != :reader')
            ->andWhere('message.deletionDatetime IS NULL')
            ->andWhere('(meta.lastReadDatetime IS NULL OR message.creationDatetime > meta.lastReadDatetime)')
            ->setParameter('band_space', $bandSpace)
            ->setParameter('reader', $reader)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $found !== null;
    }

    /**
     * Count messages sent within a date range.
     */
    public function countMessagesSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->where('message.creationDatetime >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total messages.
     */
    public function countTotalMessages(): int
    {
        return (int) $this->createQueryBuilder('message')
            ->select('COUNT(message.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count messages grouped by date within a range.
     *
     * @return array<int, array{date_label: string, count: int}>
     */
    public function countMessagesByDate(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $result = $conn->executeQuery(
            'SELECT DATE(creation_datetime) AS date_label, COUNT(id) AS count
             FROM message
             WHERE creation_datetime >= :from AND creation_datetime < :to
             GROUP BY DATE(creation_datetime)
             ORDER BY date_label ASC',
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        return array_map(
            fn (array $row): array => ['date_label' => $row['date_label'], 'count' => (int) $row['count']],
            $result->fetchAllAssociative()
        );
    }

    /**
     * Get top messagers within a date range.
     *
     * @return array<int, array{user_id: string, username: string, message_count: int, account_age_days: int, creation_datetime: \DateTimeInterface}>
     */
    public function findTopMessagers(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        /** @var array<int, array{user_id: string, username: string, message_count: string, creation_datetime: \DateTimeInterface}> $results */
        $results = $this->createQueryBuilder('message')
            ->select('IDENTITY(message.author) as user_id, u.username, COUNT(message.id) as message_count, u.creationDatetime as creation_datetime')
            ->join('message.author', 'u')
            ->where('message.creationDatetime >= :from')
            ->andWhere('message.creationDatetime < :to')
            ->groupBy('message.author, u.username, u.creationDatetime')
            ->orderBy('message_count', 'DESC')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $now = new \DateTimeImmutable();

        return array_map(function (array $row) use ($now): array {
            $creationDate = \DateTimeImmutable::createFromInterface($row['creation_datetime']);
            $diff = $now->diff($creationDate);

            return [
                'user_id' => $row['user_id'],
                'username' => $row['username'],
                'message_count' => (int) $row['message_count'],
                'account_age_days' => $diff->days,
                'creation_datetime' => $creationDate,
            ];
        }, $results);
    }
}

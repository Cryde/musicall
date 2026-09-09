<?php declare(strict_types=1);

namespace App\Service\Procedure\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Event\MessageSentEvent;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Builder\Message\MessageDirector;
use App\Service\Builder\Message\MessageParticipantDirector;
use App\Service\Builder\Message\MessageThreadDirector;
use App\Service\Builder\Message\MessageThreadMetaDirector;
use App\Service\User\UserNotificationPreferenceChecker;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MessageSenderProcedure
{
    public function __construct(
        private readonly EntityManagerInterface           $entityManager,
        private readonly MessageThreadRepository          $messageThreadRepository,
        private readonly MessageThreadDirector            $messageThreadDirector,
        private readonly MessageThreadMetaDirector        $messageThreadMetaDirector,
        private readonly MessageParticipantDirector       $messageParticipantDirector,
        private readonly MessageThreadMetaRepository      $messageThreadMetaRepository,
        private readonly MessageDirector                  $messageDirector,
        private readonly EventDispatcherInterface         $eventDispatcher,
        private readonly UserNotificationPreferenceChecker $preferenceChecker,
    ) {
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function process(User $sender, User $recipient, string $content) : Message
    {
        /** @var MessageSentEvent[] $eventsToDispatch */
        $eventsToDispatch = [];

        $message = $this->entityManager->wrapInTransaction(function () use ($sender, $recipient, $content, &$eventsToDispatch): Message {
            // Serialize concurrent A↔B thread creation by acquiring write locks on the
            // two user rows in deterministic id-sorted order. The second request in a race blocks
            // here, then sees the thread the first just created.
            $this->lockUsers([$sender, $recipient]);

            if (!$thread = $this->messageThreadRepository->findByParticipants($recipient, $sender)) {
                $thread = $this->messageThreadDirector->create();
                // The sender has read their own opening message; the recipient has read nothing.
                $threadMetaSender = $this->messageThreadMetaDirector->create($thread, $sender, new \DateTimeImmutable());
                $threadMetaRecipient = $this->messageThreadMetaDirector->create($thread, $recipient, null);
                $participantSender = $this->messageParticipantDirector->create($thread, $sender);
                $participantRecipient = $this->messageParticipantDirector->create($thread, $recipient);

                $this->entityManager->persist($thread);
                $this->entityManager->persist($threadMetaSender);
                $this->entityManager->persist($threadMetaRecipient);
                $this->entityManager->persist($participantSender);
                $this->entityManager->persist($participantRecipient);

                if ($this->shouldNotify($recipient, $threadMetaRecipient)) {
                    $threadMetaRecipient->pendingNotificationSent = true;
                    $eventsToDispatch[] = new MessageSentEvent($recipient, $sender, $thread);
                }
            } else {
                // The pair lock above serialises this path against itself, but not against
                // processByThread(), so the thread lock is what makes "every send into a thread is
                // serialised" an invariant of the procedure rather than a property of one entry point.
                // No further user lock is needed: findByParticipants() only matches a thread whose
                // participants are exactly this pair, so the two rows already locked are all of them.
                $this->lockThread($thread);
                $eventsToDispatch = array_merge(
                    $eventsToDispatch,
                    $this->handleReadMessage($thread, $sender),
                );
            }

            $message = $this->messageDirector->create($thread, $sender, $content);
            $this->entityManager->persist($message);
            $thread->lastMessage = $message;
            $this->entityManager->flush();

            return $message;
        });

        // Dispatch AFTER the transaction commits so we never send an email
        // for a message that failed to persist. The throttle decision +
        // pending-flag flip already happened inside the transaction above,
        // so the listener is now a pure email sender.
        foreach ($eventsToDispatch as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $message;
    }

    public function processByThread(MessageThread $thread, User $sender, string $content): Message
    {
        /** @var MessageSentEvent[] $eventsToDispatch */
        $eventsToDispatch = [];

        $message = $this->entityManager->wrapInTransaction(function () use ($thread, $sender, $content, &$eventsToDispatch): Message {
            // Users first, then the thread, in the same order process() uses. See lockThread().
            $this->lockUsers($this->participantsOf($thread));
            $this->lockThread($thread);

            $eventsToDispatch = $this->handleReadMessage($thread, $sender);

            $message = $this->messageDirector->create($thread, $sender, $content);
            $this->entityManager->persist($message);
            $thread->lastMessage = $message;
            $this->entityManager->flush();

            return $message;
        });

        // Same rule as process(): only emit notifications once the message is
        // actually persisted, never before.
        foreach ($eventsToDispatch as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $message;
    }

    /**
     * Serialises one send into a thread against another.
     *
     * Three things here are unsafe concurrently and this settles all three. The throttle is a
     * read-modify-write: two senders both see `pendingNotificationSent = false` for a third
     * participant and both email them. `thread->lastMessage` is last-writer-wins, so an older message
     * can end up as the thread's last and put the inbox out of order. And the find-or-create in
     * handleReadMessage() could otherwise hit `UNIQUE (thread_id, user_id)` from two sides at once.
     * Three participants is where *different* senders start colliding on a third party's row, which is
     * what a Band Space channel is (#957, mandatory before #960), but a two-person conversation is not
     * immune: one sender double submitting from two tabs races on the recipient's single flag just the
     * same, and this covers both identically. It also removes a race that was already
     * there: without an exclusive lock up front, two concurrent sends both take the foreign key shared
     * lock on the thread row when inserting their message and then both need it exclusively to write
     * `last_message_id`, which is an upgrade deadlock either way round.
     *
     * **Always after lockUsers(), never before.** The flush at the end of the transaction takes locks
     * on `fos_user` rows, both the foreign key shared locks from inserting a message and a meta row,
     * and, until User's identifier mapping is fixed, an exclusive lock from a spurious
     * `UPDATE fos_user SET id = ?` that Doctrine emits for every hydrated user. So a transaction that
     * locked the thread first would hold thread and want users while process() holds users and wants
     * thread. That is a real deadlock, reproducible with an ordinary direct message: the profile
     * page's "send a message" hits process() while the thread view hits processByThread(). Both paths
     * therefore take users in id order first and the thread second.
     *
     * Taking every participant rather than just the sender does not widen the footprint: the flush
     * locks all of them anyway through that spurious update, and handleReadMessage() hydrates every
     * one of them regardless. What it does change is *duration*, since those rows are now held from
     * the top of the transaction rather than from the flush, which is the price of the lock existing at
     * all. It cannot be narrowed to the sender on principle either: findOrCreateMetaFor() inserts a
     * row for whichever participant is missing one, and that insert takes a foreign key lock on an
     * arbitrary participant's user row.
     *
     * **What it does not cover: send against mark-as-read.** A pessimistic lock only orders
     * transactions that agree to take it, and MessageThreadMetaPatchProcessor writes the same
     * `pending_notification_sent` column without touching the thread. That race is currently
     * self-healing rather than safe: a send that reads the flag before the PATCH commits sees it still
     * set and skips one email, then the PATCH's `false` stands, so the next message emails normally.
     * The cost is one missed email in the window, never a duplicate and never a stuck flag. That is a
     * property of these two particular writes, not a guarantee from this lock, so a third writer to
     * that column has to be thought about again.
     */
    private function lockThread(MessageThread $thread): void
    {
        $this->entityManager->lock($thread, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Write-locks these users' rows in id order.
     *
     * Ascending id is the whole point: a fixed order is what stops two transactions that want the
     * same two rows from taking them in opposite orders and deadlocking.
     *
     * @param User[] $users
     */
    private function lockUsers(array $users): void
    {
        $ids = array_map(static fn (User $user): string => (string) $user->id, $users);
        sort($ids, SORT_STRING);

        $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN (:ids)')
            ->orderBy('u.id', 'ASC')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * @return User[]
     */
    private function participantsOf(MessageThread $thread): array
    {
        $users = [];
        foreach ($thread->messageParticipants as $participant) {
            $users[] = $participant->participant;
        }

        return $users;
    }

    /**
     * @return MessageSentEvent[]
     */
    private function handleReadMessage(MessageThread $thread, User $sender): array
    {
        // One query for the whole thread, rather than one per participant.
        $metas = $this->messageThreadMetaRepository->findByThreadIndexedByUserId($thread);

        $events = [];
        foreach ($thread->messageParticipants as $participant) {
            $recipient = $participant->participant;
            if ($recipient->id !== $sender->id) {
                $threadMetaRecipient = $this->findOrCreateMetaFor($thread, $recipient, $metas);
                // The recipient's read position is deliberately left alone: the message about to be
                // written is newer than it, so it already counts as unread. Rewinding the position to
                // null, which is what the boolean's `false` amounted to, would resurrect every
                // message they had already read in this thread.
                if ($this->shouldNotify($recipient, $threadMetaRecipient)) {
                    $threadMetaRecipient->pendingNotificationSent = true;
                    $events[] = new MessageSentEvent($recipient, $sender, $thread);
                }
            }
        }

        // Writing a message is reading the thread, so the sender's position moves to now. Stamped
        // before the message exists, which leaves the position at or just behind their own message;
        // that never shows as unread because the count ignores messages you wrote yourself. Same shape
        // the forum already uses, see ForumTopicParticipationService::recordPost(), which is likewise
        // find-or-create plus your own position to now.
        $senderMeta = $this->findOrCreateMetaFor($thread, $sender, $metas);
        $senderMeta->lastReadDatetime = new \DateTimeImmutable();

        return $events;
    }

    /**
     * This participant's read-state row, created if they do not have one yet.
     *
     * The two lookups this replaces were `findOneBy` plus `assert($meta !== null)`, and **assertions
     * are disabled in production**: a participant without a row did not fail loudly, their
     * notification was silently skipped, and the sender's own missing row would have been a TypeError
     * on the line after. Nothing triggers it today, because `process()` writes both rows when it
     * creates a thread. It becomes the *normal* case for a Band Space channel, where membership is
     * derived from BandSpaceMembership rather than materialised, so a member who has never opened the
     * channel has no row at all (#957, mandatory before #960).
     *
     * A new row starts with no read position, so everything already in the thread counts as unread,
     * which is the honest answer for somebody who has never looked at it.
     *
     * Creating rather than skipping is safe here only because lockThread() serialises the transaction;
     * see the note there about `UNIQUE (thread_id, user_id)`. A future writer that inserts a meta row
     * without taking that lock, a member joining a channel that already has history for instance,
     * brings the duplicate key back and would need catching. Who *deserves* a row is not decided here:
     * this trusts `thread->messageParticipants`, so filtering out members who have left stays with
     * whatever populates that list.
     *
     * Each participant appears once in `messageParticipants` (`UNIQUE (thread_id, participant_id)`)
     * and the sender is handled separately, so nothing is looked up twice and the map needs no
     * updating.
     *
     * @param array<string, MessageThreadMeta> $metas
     */
    private function findOrCreateMetaFor(MessageThread $thread, User $user, array $metas): MessageThreadMeta
    {
        $userId = (string) $user->id;
        if (isset($metas[$userId])) {
            return $metas[$userId];
        }

        $meta = $this->messageThreadMetaDirector->create($thread, $user, null);
        $this->entityManager->persist($meta);

        return $meta;
    }

    /**
     * Skip the email when the recipient was active on the site within the
     * last ACTIVE_WINDOW_SECONDS (#712). They will see the in-app
     * notification next time they hit the inbox tab; an email at that
     * point is noise. Window is comfortably wider than
     * UserActivityListener's write throttle.
     */
    private const int ACTIVE_WINDOW_SECONDS = 300;

    /**
     * One email per unread streak (#533) AND skip if the recipient was
     * recently active (#712). Send only if the recipient is eligible
     * (not deleted, notifications enabled, presently idle) AND has no
     * email already in flight for the current unread streak.
     */
    private function shouldNotify(User $recipient, MessageThreadMeta $meta): bool
    {
        if ($recipient->isDeleted()) {
            return false;
        }
        if (!$this->preferenceChecker->canReceiveMessageNotification($recipient)) {
            return false;
        }
        if ($meta->pendingNotificationSent) {
            return false;
        }

        return !$this->wasRecentlyActive($recipient);
    }

    private function wasRecentlyActive(User $recipient): bool
    {
        if ($recipient->lastActivityDatetime === null) {
            return false;
        }

        return (new \DateTimeImmutable())->getTimestamp() - $recipient->lastActivityDatetime->getTimestamp()
            < self::ACTIVE_WINDOW_SECONDS;
    }
}

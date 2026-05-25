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
            // two user rows in deterministic id-sorted order (deadlock-free). The second
            // request in a race blocks here, then sees the thread the first just created.
            $this->lockUserPair($sender, $recipient);

            if (!$thread = $this->messageThreadRepository->findByParticipants($recipient, $sender)) {
                $thread = $this->messageThreadDirector->create();
                $threadMetaSender = $this->messageThreadMetaDirector->create($thread, $sender, true);
                $threadMetaRecipient = $this->messageThreadMetaDirector->create($thread, $recipient, false);
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
        $eventsToDispatch = $this->handleReadMessage($thread, $sender);

        $message = $this->messageDirector->create($thread, $sender, $content);
        $this->entityManager->persist($message);
        $thread->lastMessage = $message;
        $this->entityManager->flush();

        // Same rule as process(): only emit notifications once the message is
        // actually persisted, never before.
        foreach ($eventsToDispatch as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $message;
    }

    private function lockUserPair(User $sender, User $recipient): void
    {
        $ids = [(string) $sender->id, (string) $recipient->id];
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
     * @return MessageSentEvent[]
     */
    private function handleReadMessage(MessageThread $thread, User $sender): array
    {
        $events = [];
        foreach ($thread->messageParticipants as $participant) {
            $recipient = $participant->participant;
            if ($recipient->id !== $sender->id) {
                $threadMetaRecipient = $this->messageThreadMetaRepository->findOneBy(['user' => $recipient, 'thread' => $thread]);
                assert($threadMetaRecipient !== null);
                $threadMetaRecipient->isRead = false;
                if ($this->shouldNotify($recipient, $threadMetaRecipient)) {
                    $threadMetaRecipient->pendingNotificationSent = true;
                    $events[] = new MessageSentEvent($recipient, $sender, $thread);
                }
            }
        }

        $threadMetaSender = $this->messageThreadMetaRepository->findOneBy(['user' => $sender, 'thread' => $thread]);
        assert($threadMetaSender !== null);
        $threadMetaSender->isRead = true;

        return $events;
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

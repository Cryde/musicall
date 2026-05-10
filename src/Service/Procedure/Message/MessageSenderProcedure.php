<?php declare(strict_types=1);

namespace App\Service\Procedure\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Event\MessageSentEvent;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Builder\Message\MessageDirector;
use App\Service\Builder\Message\MessageParticipantDirector;
use App\Service\Builder\Message\MessageThreadDirector;
use App\Service\Builder\Message\MessageThreadMetaDirector;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MessageSenderProcedure
{
    public function __construct(
        private readonly EntityManagerInterface      $entityManager,
        private readonly MessageThreadRepository     $messageThreadRepository,
        private readonly MessageThreadDirector       $messageThreadDirector,
        private readonly MessageThreadMetaDirector   $messageThreadMetaDirector,
        private readonly MessageParticipantDirector  $messageParticipantDirector,
        private readonly MessageThreadMetaRepository $messageThreadMetaRepository,
        private readonly MessageDirector             $messageDirector,
        private readonly EventDispatcherInterface    $eventDispatcher
    ) {
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function process(User $sender, User $recipient, string $content) : Message
    {
        return $this->entityManager->wrapInTransaction(function () use ($sender, $recipient, $content): Message {
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

                $this->eventDispatcher->dispatch(new MessageSentEvent($recipient, $sender, $thread));
            } else {
                $this->handleReadMessage($thread, $sender);
            }

            $message = $this->messageDirector->create($thread, $sender, $content);
            $this->entityManager->persist($message);
            $thread->lastMessage = $message;
            $this->entityManager->flush();

            return $message;
        });
    }

    public function processByThread(MessageThread $thread, User $sender, string $content): Message
    {
        $this->handleReadMessage($thread, $sender);

        $message = $this->messageDirector->create($thread, $sender, $content);
        $this->entityManager->persist($message);
        $thread->lastMessage = $message;
        $this->entityManager->flush();

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

    private function handleReadMessage(MessageThread $thread, User $sender): void
    {
        foreach ($thread->messageParticipants as $participant) {
            $recipient = $participant->participant;
            if ($recipient->id !== $sender->id) {
                $threadMetaRecipient = $this->messageThreadMetaRepository->findOneBy(['user' => $recipient, 'thread' => $thread]);
                assert($threadMetaRecipient !== null);
                $threadMetaRecipient->isRead = false;
                $this->eventDispatcher->dispatch(new MessageSentEvent($recipient, $sender, $thread));
            }
        }

        $threadMetaSender = $this->messageThreadMetaRepository->findOneBy(['user' => $sender, 'thread' => $thread]);
        assert($threadMetaSender !== null);
        $threadMetaSender->isRead = true;
    }
}

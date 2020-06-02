<?php

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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MessageSenderProcedure
{
    /**
     * @var MessageThreadRepository
     */
    private MessageThreadRepository $messageThreadRepository;
    /**
     * @var MessageDirector
     */
    private MessageDirector $messageDirector;
    /**
     * @var MessageThreadDirector
     */
    private MessageThreadDirector $messageThreadDirector;
    /**
     * @var MessageThreadMetaDirector
     */
    private MessageThreadMetaDirector $messageThreadMetaDirector;
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    /**
     * @var MessageParticipantDirector
     */
    private MessageParticipantDirector $messageParticipantDirector;
    /**
     * @var MessageThreadMetaRepository
     */
    private MessageThreadMetaRepository $messageThreadMetaRepository;
    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageThreadRepository $messageThreadRepository,
        MessageThreadDirector $messageThreadDirector,
        MessageThreadMetaDirector $messageThreadMetaDirector,
        MessageParticipantDirector $messageParticipantDirector,
        MessageThreadMetaRepository $messageThreadMetaRepository,
        MessageDirector $messageDirector,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityManager = $entityManager;
        $this->messageThreadRepository = $messageThreadRepository;
        $this->messageThreadDirector = $messageThreadDirector;
        $this->messageThreadMetaDirector = $messageThreadMetaDirector;
        $this->messageParticipantDirector = $messageParticipantDirector;
        $this->messageDirector = $messageDirector;
        $this->messageThreadMetaRepository = $messageThreadMetaRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param User   $sender
     * @param User   $recipient
     * @param string $message
     *
     * @return Message
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function process(User $sender, User $recipient, string $message) : Message
    {
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

            $this->eventDispatcher->dispatch(new MessageSentEvent($recipient), MessageSentEvent::NAME);
        } else {
            $this->handleReadMessage($thread, $sender);
        }

        $message = $this->messageDirector->create($thread, $sender, $message);
        $this->entityManager->persist($message);
        $thread->setLastMessage($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * @param MessageThread $thread
     * @param User          $sender
     * @param string        $message
     *
     * @return Message
     */
    public function processByThread(MessageThread $thread, User $sender, string $message): Message
    {
        $this->handleReadMessage($thread, $sender);

        $message = $this->messageDirector->create($thread, $sender, $message);
        $this->entityManager->persist($message);
        $thread->setLastMessage($message);
        $this->entityManager->flush();

        return $message;
    }

    private function handleReadMessage(MessageThread $thread, User $sender)
    {
        foreach ($thread->getMessageParticipants() as $participant) {
            $recipient = $participant->getParticipant();
            if ($recipient->getId() !== $sender->getId()) {
                $threadMetaRecipient = $this->messageThreadMetaRepository->findOneBy(['user' => $recipient, 'thread' => $thread]);
                $threadMetaRecipient->setIsRead(false);
                $this->eventDispatcher->dispatch(new MessageSentEvent($recipient), MessageSentEvent::NAME);
            }
        }

        $threadMetaSender = $this->messageThreadMetaRepository->findOneBy(['user' => $sender, 'thread' => $thread]);
        $threadMetaSender->setIsRead(true);
    }
}

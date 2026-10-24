<?php declare(strict_types=1);

namespace App\Security\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\User\Relation\UserContactPolicy;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Who may act in a direct conversation: typing, editing, deleting, reacting (#1242, #1245).
 */
readonly class DirectThreadChecker
{
    public function __construct(
        private MessageThreadRepository $messageThreadRepository,
        private MessageRepository $messageRepository,
        private ThreadAccess $threadAccess,
        private UserContactPolicy $contactPolicy,
    ) {
    }

    /**
     * A 404 rather than a 403 for a stranger, so a thread id cannot be probed. A band channel has no
     * participants and its own endpoints, which check band membership.
     */
    public function checkParticipantForWrite(string $threadId, User $user): MessageThread
    {
        $thread = $this->messageThreadRepository->findOneByIdWithParticipants($threadId);
        if (!$thread instanceof MessageThread
            || $thread->isChannel()
            || !$this->threadAccess->isOneOfParticipant($thread, $user)
        ) {
            throw new NotFoundHttpException('message.thread.not_found');
        }

        // A block closes the conversation both ways, for everything that would reach the other person.
        if (!$this->contactPolicy->canPostInThread($thread, $user)) {
            throw new AccessDeniedHttpException(UserContactPolicy::BLOCKED_MESSAGE);
        }

        return $thread;
    }

    /**
     * @return array{MessageThread, Message}
     */
    public function checkMessageForWrite(string $threadId, string $messageId, User $user): array
    {
        $thread = $this->checkParticipantForWrite($threadId, $user);
        $message = $this->messageRepository->findOneInThread($messageId, $thread);
        if (!$message instanceof Message) {
            throw new NotFoundHttpException('message.message.not_found');
        }

        return [$thread, $message];
    }
}

<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Event\MessageThreadTypingEvent;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\User\Relation\UserContactPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<null, void>
 */
readonly class MessageThreadTypingProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageThreadRepository $messageThreadRepository,
        private ThreadAccess $threadAccess,
        private UserContactPolicy $contactPolicy,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
        #[Target('chat_typing')]
        private RateLimiterFactoryInterface $typingLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // The band chat's budget: one person types in one place at a time.
        $this->typingLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        $thread = $this->messageThreadRepository->findOneByIdWithParticipants((string) $uriVariables['threadId']);
        // A band channel has no participants and its own endpoint, which checks band membership.
        if (!$thread instanceof MessageThread
            || $thread->isChannel()
            || !$this->threadAccess->isOneOfParticipant($thread, $user)
        ) {
            throw new NotFoundHttpException('message.thread.not_found');
        }

        // Somebody who cannot write must not look like they are writing.
        if (!$this->contactPolicy->canPostInThread($thread, $user)) {
            throw new AccessDeniedHttpException(UserContactPolicy::BLOCKED_MESSAGE);
        }

        $this->eventDispatcher->dispatch(new MessageThreadTypingEvent($thread, $user));
    }
}

<?php declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageWindow;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Message\MessageWindowReader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MessageWindow>
 */
readonly class MessageWindowProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private MessageThreadRepository $messageThreadRepository,
        private MessageRepository $messageRepository,
        private ThreadAccess $threadAccess,
        private MessageBuilder $messageBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageWindow
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // 404 for a thread the caller is not in, a channel included, as MessageCollectionProvider does.
        $thread = $this->messageThreadRepository->findOneByIdWithParticipants((string) $uriVariables['threadId']);
        if (!$thread instanceof MessageThread || !$this->threadAccess->isOneOfParticipant($thread, $user)) {
            throw new NotFoundHttpException('message.thread.not_found');
        }

        [$direction, $anchorId] = MessageWindowReader::anchorOf($context['filters'] ?? [], 'message.window.single_reference');
        $anchor = $this->messageRepository->findOneInThread($anchorId, $thread);
        if (!$anchor instanceof Message) {
            throw new NotFoundHttpException('message.message.not_found');
        }

        $read = MessageWindowReader::read(
            $direction,
            fn (int $limit, bool $inclusive): array => $this->messageRepository->findOlderInConversation($thread, $anchor, $limit, $inclusive),
            fn (int $limit): array => $this->messageRepository->findNewerInConversation($thread, $anchor, $limit),
        );

        $window = new MessageWindow();
        $window->threadId = (string) $thread->id;
        $window->messages = $this->messageBuilder->buildForViewer($read['messages'], $thread, $user);
        $window->hasOlder = $read['hasOlder'];
        $window->hasNewer = $read['hasNewer'];
        $window->totalItems = $this->messageRepository->countForThread($thread);

        return $window;
    }
}

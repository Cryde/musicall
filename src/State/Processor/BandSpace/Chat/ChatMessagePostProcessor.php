<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageCreate;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\Message\MessageMention;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Event\BandSpaceChatMentionedEvent;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\ChatMentionResolver;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ChatMessageCreate, ChatMessageResource>
 */
readonly class ChatMessagePostProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadRepository $messageThreadRepository,
        private MessageSenderProcedure $messageSenderProcedure,
        private ChatMessageBuilder $chatMessageBuilder,
        private ChatMentionResolver $chatMentionResolver,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private Security $security,
        // The same budget as a direct message, on purpose: one person has one sending allowance
        // whether they are writing to their band or to somebody in particular, and a conversation
        // that needs more than 20 messages a minute is not a band chat.
        #[Target('message_send')]
        private RateLimiterFactoryInterface $messageSendLimiter,
    ) {
    }

    /**
     * @param ChatMessageCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatMessageResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // Keyed the way both direct message processors key it, so the budget really is shared
        // rather than a second bucket of the same size.
        $this->messageSendLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace] = $this->memberChecker->checkMemberForWrite($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('Ce Band Space n\'a pas de conversation');
        }

        $message = $this->messageSenderProcedure->processByThread($channel, $user, $data->content);

        $mentionedUsers = $this->chatMentionResolver->resolve($bandSpace, $data->content);
        $this->recordMentions($message, $mentionedUsers);

        // Built before the dispatch, so a listener cannot change what the sender is answered with.
        $result = $this->chatMessageBuilder->buildItem($message, $bandSpaceId, $user);

        if ($mentionedUsers !== []) {
            $this->eventDispatcher->dispatch(new BandSpaceChatMentionedEvent($message, $bandSpace, $mentionedUsers));
        }

        return $result;
    }

    /**
     * The rows the renderer reads to name a member who has since left the band.
     *
     * Written here rather than inside MessageSenderProcedure, and after it returns rather than during:
     * that procedure is shared with direct messages, which have no mentions, and it holds pessimistic
     * write locks on every member row plus the thread for the length of its transaction. Nothing that
     * can wait for the commit belongs in there.
     *
     * Best-effort, like the notification, and for a reason that is easy to get wrong: by the time this
     * runs the message is **already committed** in its own transaction. Letting a failure here 500 the
     * request would not make the pair atomic, it would only report a message that has been sent and is
     * on everybody's screen as having failed, and the composer keeps the text on an error, so the
     * sender's natural next move is to post it a second time. Losing the rows costs `@inconnu` on one
     * message, which the renderer is built to survive; a duplicate message cannot be taken back.
     *
     * @param User[] $mentionedUsers
     */
    private function recordMentions(Message $message, array $mentionedUsers): void
    {
        if ($mentionedUsers === []) {
            return;
        }

        try {
            foreach ($mentionedUsers as $mentionedUser) {
                $this->entityManager->persist(new MessageMention($message, $mentionedUser));
            }
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Could not record the mentions of a chat message, it will render them as unknown', [
                'message_id' => (string) $message->id,
                'exception' => $e,
            ]);
        }
    }
}

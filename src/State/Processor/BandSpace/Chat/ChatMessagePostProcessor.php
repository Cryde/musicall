<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageCreate;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
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

        return $this->chatMessageBuilder->buildItem($message, $bandSpaceId);
    }
}

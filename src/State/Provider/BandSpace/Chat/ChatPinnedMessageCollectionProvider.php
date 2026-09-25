<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The channel's pinned messages, newest pin first (#969).
 *
 * @implements ProviderInterface<ChatMessageResource>
 */
readonly class ChatPinnedMessageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadRepository $messageThreadRepository,
        private MessageRepository $messageRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return ChatMessageResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // checkMember(), like the message list: reading stays open for the whole 30 day deletion
        // grace period, and the pinned bar is where the useful addresses and codes are.
        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('Ce Band Space n\'a pas de conversation');
        }

        return $this->chatMessageBuilder->buildFromProjection(
            $this->messageRepository->findPinnedForThread($channel),
            $bandSpaceId,
            $user,
            $channel,
        );
    }
}

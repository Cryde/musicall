<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageWindow;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use App\Service\Message\MessageWindowReader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ChatMessageWindow>
 */
readonly class ChatMessageWindowProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadRepository $messageThreadRepository,
        private MessageRepository $messageRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChatMessageWindow
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace, $membership] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('band_space.chat.no_conversation');
        }

        [$direction, $anchorId] = MessageWindowReader::anchorOf($context['filters'] ?? [], 'band_space.chat.window_single_reference');
        $anchor = $this->messageRepository->findOneInThread($anchorId, $channel);
        if (!$anchor instanceof Message) {
            throw new NotFoundHttpException('band_space.chat.message_not_found');
        }

        $read = MessageWindowReader::read(
            $direction,
            fn (int $limit, bool $inclusive): array => $this->messageRepository->findOlderInThread($channel, $anchor, $limit, $inclusive),
            fn (int $limit): array => $this->messageRepository->findNewerInThread($channel, $anchor, $limit),
        );

        $window = new ChatMessageWindow();
        $window->bandSpaceId = $bandSpaceId;
        $window->messages = $this->chatMessageBuilder->buildFromProjection($read['messages'], $bandSpaceId, $membership, $channel);
        $window->hasOlder = $read['hasOlder'];
        $window->hasNewer = $read['hasNewer'];
        $window->totalItems = $this->messageRepository->countForThread($channel);

        return $window;
    }
}

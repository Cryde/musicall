<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
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
 * @implements ProviderInterface<ChatMessageResource>
 */
readonly class ChatMessageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadRepository $messageThreadRepository,
        private MessageRepository $messageRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private Security $security,
        private Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // checkMember(), not checkMemberForWrite(): reading stays open for the whole 30 day deletion
        // grace period, like every other module.
        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $channel = $this->messageThreadRepository->findChannelForBandSpace($bandSpace);
        if (!$channel instanceof MessageThread) {
            throw new NotFoundHttpException('Ce Band Space n\'a pas de conversation');
        }

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $rows = $this->messageRepository->findForThread($channel, $itemsPerPage, $offset);

        return new TraversablePaginator(
            new \ArrayIterator($this->chatMessageBuilder->buildFromProjection($rows, $bandSpaceId)),
            $page,
            $itemsPerPage,
            $this->messageRepository->countForThread($channel),
        );
    }
}

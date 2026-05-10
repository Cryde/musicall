<?php

declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageResource;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<MessageResource>
 */
readonly class MessageCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Security                $security,
        private MessageThreadRepository $messageThreadRepository,
        private ThreadAccess            $threadAccess,
        private CollectionProvider      $collectionProvider,
        private MessageBuilder          $messageBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        if (!($thread = $this->messageThreadRepository->find($uriVariables['threadId'])) instanceof MessageThread) {
            throw new NotFoundHttpException('Thread not found.');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        if (!$this->threadAccess->isOneOfParticipant($thread, $user)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à voir ceci.');
        }

        /** @var TraversablePaginator $paginator */
        $paginator = $this->collectionProvider->provide($operation, $uriVariables, $context);

        $dtos = array_map(
            fn (Message $entity): MessageResource => $this->messageBuilder->buildItem($entity),
            iterator_to_array($paginator),
        );

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $paginator->getCurrentPage(),
            $paginator->getItemsPerPage(),
            $paginator->getTotalItems(),
        );
    }
}

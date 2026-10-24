<?php

declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageResource;
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
            throw new AccessDeniedException('common.not_authenticated');
        }
        // With the participants and their profiles: the access check walks them, and so does « Vu »,
        // which would otherwise load the other person and their profile rows on their own (#1243).
        $thread = $this->messageThreadRepository->findOneByIdWithParticipants((string) $uriVariables['threadId']);
        if (!$thread instanceof MessageThread) {
            throw new NotFoundHttpException('message.thread.not_found');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        // 404 rather than 403, matching MessageThreadItemProvider: a 403 confirms to an authenticated
        // caller that somebody else's thread exists. A Band Space channel reaches this too and has no
        // participant rows at all, so it is a 404 here for everyone, members included: a channel is
        // read through the chat endpoints and never through this one (#994).
        if (!$this->threadAccess->isOneOfParticipant($thread, $user)) {
            throw new NotFoundHttpException('message.thread.not_found');
        }

        /** @var TraversablePaginator $paginator */
        $paginator = $this->collectionProvider->provide($operation, $uriVariables, $context);

        $dtos = $this->messageBuilder->buildForViewer(iterator_to_array($paginator), $thread, $user);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $paginator->getCurrentPage(),
            $paginator->getItemsPerPage(),
            $paginator->getTotalItems(),
        );
    }
}

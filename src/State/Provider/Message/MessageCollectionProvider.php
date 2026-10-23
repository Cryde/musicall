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
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageContactOriginRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Message\ReadReceiptPolicy;
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
        private MessageContactOriginRepository $messageContactOriginRepository,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private ReadReceiptPolicy $readReceiptPolicy,
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

        $messages = iterator_to_array($paginator);
        // One query for the page's origin cards (#998), never one per message.
        $origins = $this->messageContactOriginRepository->findByMessageIds(
            array_map(static fn (Message $entity): string => (string) $entity->id, $messages),
        );
        $seenUntil = $this->seenUntil($thread, $user);
        $dtos = array_map(
            fn (Message $entity): MessageResource => $this->messageBuilder->buildItem(
                $entity,
                contactOrigin: $origins[(string) $entity->id] ?? null,
                isSeen: $seenUntil instanceof \DateTimeImmutable
                    && $entity->author->id === $user->id
                    && !$entity->isDeleted()
                    && $entity->creationDatetime <= $seenUntil,
            ),
            $messages,
        );

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $paginator->getCurrentPage(),
            $paginator->getItemsPerPage(),
            $paginator->getTotalItems(),
        );
    }

    /**
     * How far the other person has seen the conversation, or null when « Vu » does not apply between
     * the two (#1243). One lookup per page: a seen position belongs to the reader, not to a message.
     */
    private function seenUntil(MessageThread $thread, User $user): ?\DateTimeImmutable
    {
        foreach ($thread->messageParticipants as $participant) {
            $other = $participant->participant;
            if ($other->id === $user->id) {
                continue;
            }
            if (!$this->readReceiptPolicy->appliesBetween($user, $other)) {
                return null;
            }
            $meta = $this->messageThreadMetaRepository->findOneBy(['thread' => $thread, 'user' => $other]);

            return $meta instanceof MessageThreadMeta ? $meta->lastSeenDatetime : null;
        }

        return null;
    }
}

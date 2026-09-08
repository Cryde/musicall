<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageRepository;

readonly class MessageThreadMetaBuilder
{
    public function __construct(
        private MessageThreadBuilder $messageThreadBuilder,
        private MessageRepository $messageRepository,
    ) {
    }

    /**
     * @param MessageThreadMeta[] $entities every one of them belonging to $user
     *
     * @return MessageThreadMetaResource[]
     */
    public function buildList(array $entities, User $user): array
    {
        if ($entities === []) {
            return [];
        }

        // One grouped query for the whole list, not one per thread.
        $unreadCounts = $this->messageRepository->countUnreadByThreadForUser($user);

        return array_map(
            fn (MessageThreadMeta $entity): MessageThreadMetaResource => $this->build(
                $entity,
                $unreadCounts[(string) $entity->thread->id] ?? 0,
            ),
            $entities,
        );
    }

    public function buildItem(MessageThreadMeta $entity): MessageThreadMetaResource
    {
        return $this->build($entity, $this->messageRepository->countUnreadForThread($entity));
    }

    private function build(MessageThreadMeta $entity, int $unreadCount): MessageThreadMetaResource
    {
        $dto = new MessageThreadMetaResource();
        $dto->id = (string) $entity->id;
        $dto->unreadCount = $unreadCount;
        $dto->thread = $this->messageThreadBuilder->buildItem($entity->thread);

        return $dto;
    }
}

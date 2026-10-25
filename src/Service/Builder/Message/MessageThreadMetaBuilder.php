<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\Message;
use App\Entity\Message\MessageContactOrigin;
use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageContactOriginRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Translation\DisplayNameTranslator;

readonly class MessageThreadMetaBuilder
{
    public function __construct(
        private MessageThreadBuilder $messageThreadBuilder,
        private MessageRepository $messageRepository,
        private MessageMentionRepository $messageMentionRepository,
        private MessageContactOriginRepository $messageContactOriginRepository,
        private DisplayNameTranslator $displayNames,
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
        // Same rule for the previews: one query for every row's mentions, never one per row (#994).
        $mentions = $this->messageMentionRepository->findUsernamesByMessageIds(
            $this->lastMessageIds($entities),
            $this->displayNames->deletedUser(),
        );

        // And one for the line recalling why each conversation exists (#998).
        $origins = $this->messageContactOriginRepository->findLatestByThreads(
            array_map(static fn (MessageThreadMeta $entity): MessageThread => $entity->thread, $entities),
        );

        // And one for which conversations already hold a reply, the gate on sending media (#1247).
        $repliedThreadIds = $this->messageRepository->findThreadIdsWithMessageFromOtherThan(
            array_values(array_map(static fn (MessageThreadMeta $entity): string => (string) $entity->thread->id, $entities)),
            $user,
        );

        return array_map(
            fn (MessageThreadMeta $entity): MessageThreadMetaResource => $this->build(
                $entity,
                $unreadCounts[(string) $entity->thread->id] ?? 0,
                $mentions,
                $origins[(string) $entity->thread->id] ?? null,
                in_array((string) $entity->thread->id, $repliedThreadIds, true),
            ),
            $entities,
        );
    }

    public function buildItem(MessageThreadMeta $entity): MessageThreadMetaResource
    {
        return $this->build(
            $entity,
            $this->messageRepository->countUnreadForThread($entity),
            $this->messageMentionRepository->findUsernamesByMessageIds($this->lastMessageIds([$entity]), $this->displayNames->deletedUser()),
            $this->messageContactOriginRepository->findLatestByThreads([$entity->thread])[(string) $entity->thread->id] ?? null,
            $this->messageRepository->hasMessageFromOtherThan($entity->thread, $entity->user),
        );
    }

    /**
     * @param MessageThreadMeta[] $entities
     *
     * @return string[]
     */
    private function lastMessageIds(array $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            if ($entity->thread->lastMessage instanceof Message) {
                $ids[] = (string) $entity->thread->lastMessage->id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, array<string, string>> $mentions message id => (user id => username)
     */
    private function build(MessageThreadMeta $entity, int $unreadCount, array $mentions, ?MessageContactOrigin $latestContactOrigin, bool $canSendMedia): MessageThreadMetaResource
    {
        $lastMessage = $entity->thread->lastMessage;
        $dto = new MessageThreadMetaResource();
        $dto->id = (string) $entity->id;
        $dto->unreadCount = $unreadCount;
        $dto->thread = $this->messageThreadBuilder->buildItem(
            $entity->thread,
            $lastMessage instanceof Message ? ($mentions[(string) $lastMessage->id] ?? []) : [],
            $latestContactOrigin,
            $canSendMedia,
        );

        return $dto;
    }
}

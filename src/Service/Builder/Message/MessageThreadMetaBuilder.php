<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\MessageThreadMeta;

readonly class MessageThreadMetaBuilder
{
    public function __construct(
        private MessageThreadBuilder $messageThreadBuilder,
    ) {
    }

    /**
     * @param MessageThreadMeta[] $entities
     *
     * @return MessageThreadMetaResource[]
     */
    public function buildList(array $entities): array
    {
        return array_map(
            fn (MessageThreadMeta $entity): MessageThreadMetaResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(MessageThreadMeta $entity): MessageThreadMetaResource
    {
        $dto = new MessageThreadMetaResource();
        $dto->id = (string) $entity->id;
        $dto->isRead = $entity->isRead;
        $dto->thread = $this->messageThreadBuilder->buildItem($entity->thread);

        return $dto;
    }
}

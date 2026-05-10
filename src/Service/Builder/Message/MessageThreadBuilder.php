<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageThreadResource;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;

readonly class MessageThreadBuilder
{
    public function __construct(
        private MessageBuilder            $messageBuilder,
        private MessageParticipantBuilder $messageParticipantBuilder,
    ) {
    }

    public function buildItem(MessageThread $entity): MessageThreadResource
    {
        $dto = new MessageThreadResource();
        $dto->id = (string) $entity->id;
        $dto->messageParticipants = $this->messageParticipantBuilder->buildList(
            $entity->messageParticipants->toArray(),
        );
        $dto->lastMessage = $entity->lastMessage instanceof Message
            ? $this->messageBuilder->buildItem($entity->lastMessage)
            : null;

        return $dto;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageThreadResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;

readonly class MessageThreadBuilder
{
    public function __construct(
        private MessageBuilder            $messageBuilder,
        private MessageParticipantBuilder $messageParticipantBuilder,
    ) {
    }

    /**
     * @param array<string, string> $lastMessageMentionUsernamesById passed in rather than looked up
     *                                                              here so the inbox resolves every
     *                                                              row's mentions in one query (#994)
     */
    public function buildItem(MessageThread $entity, array $lastMessageMentionUsernamesById = []): MessageThreadResource
    {
        $dto = new MessageThreadResource();
        $dto->id = (string) $entity->id;
        $dto->messageParticipants = $this->messageParticipantBuilder->buildList(
            $entity->messageParticipants->toArray(),
        );
        $dto->lastMessage = $entity->lastMessage instanceof Message
            ? $this->messageBuilder->buildItem($entity->lastMessage, $lastMessageMentionUsernamesById)
            : null;

        // A channel, so the inbox can label it and reach the chat API (#994). Read straight off the
        // thread rather than passed in: which space a channel belongs to is a fact of the thread.
        if ($entity->bandSpace instanceof BandSpace) {
            $dto->bandSpaceId = (string) $entity->bandSpace->id;
            $dto->bandSpaceName = $entity->bandSpace->name;
            $dto->channelName = $entity->name;
        }

        return $dto;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageParticipantResource;
use App\Entity\Message\MessageParticipant;

readonly class MessageParticipantBuilder
{
    /**
     * @param MessageParticipant[] $entities
     *
     * @return MessageParticipantResource[]
     */
    public function buildList(array $entities): array
    {
        return array_map(
            fn (MessageParticipant $entity): MessageParticipantResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(MessageParticipant $entity): MessageParticipantResource
    {
        $dto = new MessageParticipantResource();
        $dto->id = (string) $entity->id;
        $dto->participant = $entity->participant;

        return $dto;
    }
}

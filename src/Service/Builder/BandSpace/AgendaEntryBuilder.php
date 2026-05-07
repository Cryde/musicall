<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\AgendaEntryResource;
use App\Entity\BandSpace\AgendaEntry;

readonly class AgendaEntryBuilder
{
    /**
     * @param AgendaEntry[] $entities
     * @return AgendaEntryResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(AgendaEntry $entity): AgendaEntryResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(AgendaEntry $entity): AgendaEntryResource
    {
        $dto = new AgendaEntryResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->title = $entity->title;
        $dto->description = $entity->description;
        $dto->location = $entity->location;
        $dto->eventDatetime = $entity->eventDatetime->format(\DateTimeInterface::ATOM);
        $dto->endDatetime = $entity->endDatetime?->format(\DateTimeInterface::ATOM);
        $dto->creatorId = $entity->creator !== null ? (string) $entity->creator->id : null;
        $dto->creatorUsername = $entity->creator?->username;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}

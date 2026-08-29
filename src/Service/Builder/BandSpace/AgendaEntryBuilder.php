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
        $dto->eventDatetime = $entity->eventDatetime;
        $dto->endDatetime = $entity->endDatetime;
        $dto->isAllDay = $entity->isAllDay;
        $dto->recurrenceFrequency = $entity->recurrenceFrequency?->value;
        $dto->recurrenceUntilDate = $entity->recurrenceUntilDate?->format('Y-m-d');
        $dto->recurrenceMonthlyMode = $entity->recurrenceMonthlyMode?->value;
        $dto->creatorId = (string) $entity->creator->id;
        $dto->creatorUsername = $entity->creator->username;
        $dto->creationDatetime = $entity->creationDatetime;

        return $dto;
    }
}

<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\BandSpace\FinanceRecurrence;
use DateTimeInterface;

readonly class FinanceRecurrenceBuilder
{
    /**
     * @param FinanceRecurrence[] $entities
     * @return FinanceRecurrenceResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (FinanceRecurrence $entity): FinanceRecurrenceResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(FinanceRecurrence $entity): FinanceRecurrenceResource
    {
        $dto = new FinanceRecurrenceResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->category->bandSpace->id;
        $dto->categoryId = (string) $entity->category->id;
        $dto->categoryName = $entity->category->name;
        $dto->label = $entity->label;
        $dto->type = $entity->type->value;
        $dto->amount = $entity->amount;
        $dto->scope = $entity->scope->value;
        $dto->interval = $entity->interval->value;
        $dto->startDate = $entity->startDate->format(DateTimeInterface::ATOM);
        $dto->endDate = $entity->endDate->format(DateTimeInterface::ATOM);
        $dto->isActive = $entity->isActive;
        $dto->entryCount = $entity->entries->count();
        $dto->creationDatetime = $entity->creationDatetime->format(DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(DateTimeInterface::ATOM);

        return $dto;
    }
}

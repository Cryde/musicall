<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceEntryResource;
use App\Entity\BandSpace\FinanceEntry;
use App\Enum\BandSpace\MembershipStatus;

readonly class FinanceEntryBuilder
{
    /**
     * @param FinanceEntry[] $entities
     * @param array<string, bool> $splitWarnings keyed by entry ID
     * @return FinanceEntryResource[]
     */
    public function buildFromList(array $entities, array $splitWarnings = []): array
    {
        return array_map(
            fn (FinanceEntry $entity): FinanceEntryResource => $this->buildItem(
                $entity,
                $splitWarnings[(string) $entity->id] ?? false
            ),
            $entities
        );
    }

    public function buildItem(FinanceEntry $entity, bool $splitWarning = false): FinanceEntryResource
    {
        $dto = new FinanceEntryResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->category->bandSpace->id;
        $dto->categoryId = (string) $entity->category->id;
        $dto->categoryName = $entity->category->name;
        $dto->label = $entity->label;
        $dto->type = $entity->type->value;
        $dto->status = $entity->status->value;
        $dto->amount = $entity->amount;
        $dto->amountMin = $entity->amountMin;
        $dto->amountMax = $entity->amountMax;
        $dto->date = $entity->date->format('Y-m-d');
        $dto->scope = $entity->scope->value;
        $dto->memberId = $entity->member instanceof \App\Entity\BandSpace\BandSpaceMembership ? (string) $entity->member->id : null;
        $dto->memberName = $entity->member?->user->username;
        $dto->isFormerMember = $entity->member instanceof \App\Entity\BandSpace\BandSpaceMembership && $entity->member->status !== MembershipStatus::Active;
        $dto->recurrenceId = $entity->recurrence instanceof \App\Entity\BandSpace\FinanceRecurrence ? (string) $entity->recurrence->id : null;
        $dto->splitWarning = $splitWarning;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}

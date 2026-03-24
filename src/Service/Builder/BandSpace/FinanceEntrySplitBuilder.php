<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceEntrySplitResource;
use App\Entity\BandSpace\FinanceEntrySplit;
use App\Enum\BandSpace\MembershipStatus;

readonly class FinanceEntrySplitBuilder
{
    /**
     * @param FinanceEntrySplit[] $entities
     * @return FinanceEntrySplitResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (FinanceEntrySplit $entity): FinanceEntrySplitResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(FinanceEntrySplit $entity): FinanceEntrySplitResource
    {
        $dto = new FinanceEntrySplitResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->entry->category->bandSpace->id;
        $dto->entryId = (string) $entity->entry->id;
        $dto->memberId = $entity->member !== null ? (string) $entity->member->id : null;
        $dto->memberName = $entity->member?->user->username;
        $dto->isFormerMember = $entity->member !== null && $entity->member->status !== MembershipStatus::Active;
        $dto->amount = $entity->amount;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}

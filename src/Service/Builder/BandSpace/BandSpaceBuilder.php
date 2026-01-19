<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpace as BandSpaceDTO;
use App\Entity\BandSpace\BandSpace as BandSpaceEntity;

readonly class BandSpaceBuilder
{
    /**
     * @param BandSpaceEntity[] $entities
     * @return BandSpaceDTO[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(BandSpaceEntity $entity): BandSpaceDTO => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(BandSpaceEntity $entity): BandSpaceDTO
    {
        $dto = new BandSpaceDTO();
        $dto->id = (string) $entity->id;
        $dto->name = $entity->name;

        return $dto;
    }
}

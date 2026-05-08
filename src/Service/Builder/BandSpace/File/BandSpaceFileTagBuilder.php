<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileTagResource;
use App\Entity\BandSpace\BandSpaceFileTag;

readonly class BandSpaceFileTagBuilder
{
    public function buildItem(BandSpaceFileTag $entity, int $fileCount = 0): BandSpaceFileTagResource
    {
        $dto = new BandSpaceFileTagResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->colorHex = $entity->colorHex;
        $dto->fileCount = $fileCount;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    /**
     * @param BandSpaceFileTag[] $entities
     * @param array<string, int> $fileCounts tag id => count, missing keys default to 0
     *
     * @return BandSpaceFileTagResource[]
     */
    public function buildFromList(array $entities, array $fileCounts = []): array
    {
        return array_map(
            fn (BandSpaceFileTag $entity): BandSpaceFileTagResource => $this->buildItem(
                $entity,
                $fileCounts[(string) $entity->id] ?? 0,
            ),
            $entities,
        );
    }
}

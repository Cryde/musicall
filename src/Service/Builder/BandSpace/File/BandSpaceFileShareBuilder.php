<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileShareResource;
use App\Entity\BandSpace\BandSpaceFileShare;

readonly class BandSpaceFileShareBuilder
{
    public function buildItem(BandSpaceFileShare $entity, \DateTimeImmutable $now): BandSpaceFileShareResource
    {
        $file = $entity->bandSpaceFile;

        $dto = new BandSpaceFileShareResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $file->bandSpace->id;
        $dto->fileId = (string) $file->id;
        $dto->fileOriginalName = $file->originalName;
        $dto->expiryDatetime = $entity->expiryDatetime?->format(\DateTimeInterface::ATOM);
        $dto->revocationDatetime = $entity->revocationDatetime?->format(\DateTimeInterface::ATOM);
        $dto->accessCount = $entity->accessCount;
        $dto->lastAccessDatetime = $entity->lastAccessDatetime?->format(\DateTimeInterface::ATOM);
        $dto->hasPassword = $entity->passwordHash !== null;
        $dto->isActive = $entity->revocationDatetime === null
            && ($entity->expiryDatetime === null || $entity->expiryDatetime > $now);
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        if ($entity->createdBy !== null) {
            $dto->createdBy = [
                'id' => (string) $entity->createdBy->id,
                'username' => $entity->createdBy->username,
            ];
        }

        return $dto;
    }

    /**
     * @param BandSpaceFileShare[] $entities
     *
     * @return BandSpaceFileShareResource[]
     */
    public function buildFromList(array $entities, \DateTimeImmutable $now): array
    {
        return array_map(
            fn (BandSpaceFileShare $entity): BandSpaceFileShareResource => $this->buildItem($entity, $now),
            $entities,
        );
    }
}

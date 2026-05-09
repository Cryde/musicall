<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileActivityResource;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\BandSpaceFile;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class BandSpaceFileActivityBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @param BandSpaceActivity[] $entities
     * @return BandSpaceFileActivityResource[]
     */
    public function buildFromList(BandSpaceFile $file, array $entities): array
    {
        $result = [];
        foreach ($entities as $entity) {
            if ($entity->actor === null) {
                continue;
            }
            $result[] = $this->buildItem($file, $entity);
        }

        return $result;
    }

    public function buildItem(BandSpaceFile $file, BandSpaceActivity $entity): BandSpaceFileActivityResource
    {
        if ($entity->actor === null) {
            throw new \LogicException('Cannot build BandSpaceFileActivityResource without an actor.');
        }

        $dto = new BandSpaceFileActivityResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $file->bandSpace->id;
        $dto->fileId = (string) $file->id;
        $dto->actorId = (string) $entity->actor->id;
        $dto->actorUsername = $entity->actor->username;
        $dto->actorProfilePictureUrl = $this->profilePictureUrlBuilder->build($entity->actor);
        $dto->type = $entity->type;
        $dto->payload = $entity->payload;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}

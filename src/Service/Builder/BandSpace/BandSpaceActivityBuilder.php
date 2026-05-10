<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceActivityResource;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class BandSpaceActivityBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @param BandSpaceActivity[] $entities
     * @return BandSpaceActivityResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(fn(BandSpaceActivity $entity): BandSpaceActivityResource => $this->buildItem($entity), $entities);
    }

    public function buildItem(BandSpaceActivity $entity): BandSpaceActivityResource
    {
        $dto = new BandSpaceActivityResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->module = $entity->module->value;
        $dto->resourceId = $entity->resourceId?->toString();
        $dto->type = $entity->type;
        $dto->payload = $entity->payload;
        $dto->actor = $entity->actor instanceof \App\Entity\User ? [
            'id' => (string) $entity->actor->id,
            'username' => $entity->actor->username,
            'profile_picture_url' => $this->profilePictureUrlBuilder->build($entity->actor),
        ] : null;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}

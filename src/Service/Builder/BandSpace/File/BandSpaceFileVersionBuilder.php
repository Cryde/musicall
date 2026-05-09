<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileVersionResource;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class BandSpaceFileVersionBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildItem(BandSpaceFileVersion $entity, ?int $currentVersionNumber): BandSpaceFileVersionResource
    {
        $file = $entity->bandSpaceFile;

        $dto = new BandSpaceFileVersionResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $file->bandSpace->id;
        $dto->fileId = (string) $file->id;
        $dto->versionNumber = $entity->versionNumber;
        $dto->size = $entity->size;
        $dto->mimeType = $entity->mimeType;
        $dto->isCurrent = $currentVersionNumber !== null && $currentVersionNumber === $entity->versionNumber;
        $dto->downloadUrl = $this->urlGenerator->generate(
            'api_band_space_files_version_download',
            [
                'bandSpaceId' => (string) $file->bandSpace->id,
                'id' => (string) $file->id,
                'versionNumber' => $entity->versionNumber,
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        if ($entity->createdBy !== null) {
            $dto->createdBy = [
                'id' => (string) $entity->createdBy->id,
                'username' => $entity->createdBy->username,
                'profile_picture_url' => $this->profilePictureUrlBuilder->build($entity->createdBy),
            ];
        }

        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    /**
     * @param BandSpaceFileVersion[] $entities
     *
     * @return BandSpaceFileVersionResource[]
     */
    public function buildFromList(array $entities, ?int $currentVersionNumber): array
    {
        return array_map(
            fn (BandSpaceFileVersion $entity): BandSpaceFileVersionResource => $this->buildItem($entity, $currentVersionNumber),
            $entities,
        );
    }
}

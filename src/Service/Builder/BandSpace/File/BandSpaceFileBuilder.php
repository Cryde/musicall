<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class BandSpaceFileBuilder
{
    public function __construct(
        private BandSpaceFileRepository $fileRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param BandSpaceFile[] $entities
     *
     * @return BandSpaceFileResource[]
     */
    public function buildFromList(array $entities): array
    {
        $fileIds = array_map(fn (BandSpaceFile $file): string => (string) $file->id, $entities);
        $versionCounts = $this->fileRepository->countVersionsByFileIds($fileIds);

        return array_map(
            fn (BandSpaceFile $entity): BandSpaceFileResource => $this->buildItem(
                $entity,
                $versionCounts[(string) $entity->id] ?? 0,
            ),
            $entities,
        );
    }

    public function buildItem(BandSpaceFile $entity, ?int $versionCount = null): BandSpaceFileResource
    {
        $dto = new BandSpaceFileResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->originalName = $entity->originalName;
        $dto->size = $entity->currentVersion?->size;
        $dto->mimeType = $entity->currentVersion?->mimeType;
        $dto->folderId = $entity->folder !== null ? (string) $entity->folder->id : null;
        $dto->folderPath = $this->fileRepository->buildFolderPath($entity->folder);

        $dto->tags = array_values(array_map(
            fn (BandSpaceFileTag $tag): array => [
                'id' => (string) $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->colorHex,
            ],
            $entity->tags->toArray(),
        ));

        $dto->attachedSourceType = $entity->attachedSourceType;
        $dto->attachedSourceId = $entity->attachedSourceId !== null
            ? (string) $entity->attachedSourceId
            : null;
        $dto->currentVersionId = $entity->currentVersion !== null
            ? (string) $entity->currentVersion->id
            : null;

        $dto->versionCount = $versionCount ?? $this->fileRepository->countVersionsByFileIds([(string) $entity->id])[(string) $entity->id] ?? 0;

        if ($entity->createdBy !== null) {
            $dto->createdBy = [
                'id' => (string) $entity->createdBy->id,
                'username' => $entity->createdBy->username,
                'profile_picture_url' => $this->profilePictureUrlBuilder->build($entity->createdBy),
            ];
        }

        $dto->downloadUrl = $this->urlGenerator->generate(
            'api_band_space_files_get_item',
            [
                'bandSpaceId' => (string) $entity->bandSpace->id,
                'id' => (string) $entity->id,
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        ) . '/download';

        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}

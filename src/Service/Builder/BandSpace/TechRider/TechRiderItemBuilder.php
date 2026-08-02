<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\Entity\BandSpace\TechRiderItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class TechRiderItemBuilder
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param TechRiderItem[] $entities
     * @return TechRiderItemResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (TechRiderItem $entity): TechRiderItemResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(TechRiderItem $entity): TechRiderItemResource
    {
        $dto = new TechRiderItemResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->techRider->bandSpace->id;
        $dto->riderId = (string) $entity->techRider->id;
        $dto->type = $entity->type->value;
        $dto->isIncluded = $entity->isIncluded;
        $dto->title = $entity->title;
        $dto->content = $entity->content;
        $dto->fileId = $entity->file === null ? null : (string) $entity->file->id;
        $dto->file = $this->buildFile($entity);
        $dto->position = $entity->position;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }

    /**
     * `is_archived` is part of the payload because a rider referencing a trashed file must
     * warn rather than render nothing, and the client cannot tell from the id alone.
     *
     * @return array<string, mixed>|null
     */
    private function buildFile(TechRiderItem $entity): ?array
    {
        $file = $entity->file;
        if ($file === null) {
            return null;
        }

        return [
            'id' => (string) $file->id,
            'original_name' => $file->originalName,
            'mime_type' => $file->currentVersion?->mimeType,
            'is_archived' => $file->isArchived(),
            'download_url' => $this->urlGenerator->generate('api_band_space_files_download', [
                'bandSpaceId' => (string) $entity->techRider->bandSpace->id,
                'id' => (string) $file->id,
            ]),
        ];
    }
}

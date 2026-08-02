<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\BandSpace\TechRiderPatchRow;
use App\Enum\BandSpace\TechRiderPatchDirection;
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
        $dto->patchList = $this->buildPatchList($entity);
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

    /**
     * An empty patch list serialises as two empty arrays rather than null, so the client can
     * tell "this item is a grid with nothing in it yet" from "this item is not a grid at all".
     *
     * Partitioned and sorted in PHP because the rows are already loaded: a rider fetch joins
     * them, so asking the database for them again in two ordered queries would be slower.
     *
     * @return array<string, mixed>|null
     */
    private function buildPatchList(TechRiderItem $entity): ?array
    {
        if (!$entity->type->storesRelationalRows()) {
            return null;
        }

        $grouped = [
            TechRiderPatchDirection::Input->value => [],
            TechRiderPatchDirection::Output->value => [],
        ];

        foreach ($entity->patchRows as $row) {
            $grouped[$row->direction->value][] = $row;
        }

        foreach ($grouped as $direction => $rows) {
            usort($rows, static fn (TechRiderPatchRow $a, TechRiderPatchRow $b): int => $a->position <=> $b->position);
            $grouped[$direction] = array_map(fn (TechRiderPatchRow $row): array => $this->buildPatchRow($row), $rows);
        }

        return [
            'inputs' => $grouped[TechRiderPatchDirection::Input->value],
            'outputs' => $grouped[TechRiderPatchDirection::Output->value],
        ];
    }

    /**
     * The colour ships as both the stored name and its hex. The name is what a client compares
     * against the palette, the hex is what it paints with, and deriving one from the other on
     * the client would be a second copy of the palette.
     *
     * @return array<string, mixed>
     */
    private function buildPatchRow(TechRiderPatchRow $row): array
    {
        return [
            'id' => (string) $row->id,
            'channel' => $row->channel,
            'name' => $row->name,
            'microphone' => $row->microphone,
            'routing' => $row->routing,
            'colour' => $row->colour?->value,
            'colour_hex' => $row->colour?->hex(),
            'position' => $row->position,
        ];
    }
}

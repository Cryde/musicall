<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\BandSpaceFolderResource;
use App\Entity\BandSpace\BandSpaceFolder;

readonly class BandSpaceFolderBuilder
{
    public function buildItem(BandSpaceFolder $entity, int $depth = 0): BandSpaceFolderResource
    {
        $dto = new BandSpaceFolderResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->parentId = $entity->parent !== null ? (string) $entity->parent->id : null;
        $dto->depth = $depth;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    /**
     * Assembles a flat list of folders into a tree of root-level resources,
     * each carrying its full nested subtree under `children` as inlined arrays.
     *
     * @param BandSpaceFolder[] $entities
     *
     * @return BandSpaceFolderResource[]
     */
    public function buildTree(array $entities): array
    {
        /** @var array<string, BandSpaceFolder[]> $childrenByParentId */
        $childrenByParentId = [];
        /** @var BandSpaceFolder[] $roots */
        $roots = [];
        foreach ($entities as $entity) {
            if ($entity->parent === null) {
                $roots[] = $entity;
                continue;
            }
            $childrenByParentId[(string) $entity->parent->id][] = $entity;
        }

        $resources = [];
        foreach ($roots as $root) {
            $rootDto = $this->buildItem($root, 0);
            $rootDto->children = $this->assembleChildren($root, $childrenByParentId, 1);
            $resources[] = $rootDto;
        }

        return $resources;
    }

    /**
     * @param array<string, BandSpaceFolder[]> $childrenByParentId
     *
     * @return array<int, array<string, mixed>>
     */
    private function assembleChildren(BandSpaceFolder $node, array $childrenByParentId, int $depth): array
    {
        $children = $childrenByParentId[(string) $node->id] ?? [];

        return array_map(
            fn (BandSpaceFolder $child): array => $this->toNestedArray($child, $childrenByParentId, $depth),
            $children,
        );
    }

    /**
     * @param array<string, BandSpaceFolder[]> $childrenByParentId
     *
     * @return array<string, mixed>
     */
    private function toNestedArray(BandSpaceFolder $node, array $childrenByParentId, int $depth): array
    {
        return [
            'id' => (string) $node->id,
            'band_space_id' => (string) $node->bandSpace->id,
            'name' => $node->name,
            'parent_id' => $node->parent !== null ? (string) $node->parent->id : null,
            'depth' => $depth,
            'children' => $this->assembleChildren($node, $childrenByParentId, $depth + 1),
            'creation_datetime' => $node->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $node->updateDatetime?->format(\DateTimeInterface::ATOM),
        ];
    }
}

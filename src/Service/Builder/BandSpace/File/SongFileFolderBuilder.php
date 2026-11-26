<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\File;

use App\ApiResource\BandSpace\File\SongFileFolderResource;
use App\Entity\BandSpace\SongFileFolder;

readonly class SongFileFolderBuilder
{
    public function buildItem(SongFileFolder $folder): SongFileFolderResource
    {
        $dto = new SongFileFolderResource();
        $dto->id = (string) $folder->id;
        $dto->bandSpaceId = (string) $folder->song->bandSpace->id;
        $dto->songId = (string) $folder->song->id;
        $dto->parentId = $folder->parent instanceof SongFileFolder ? (string) $folder->parent->id : null;
        $dto->name = $folder->name;
        $dto->creationDatetime = $folder->creationDatetime;

        return $dto;
    }

    /**
     * @param SongFileFolder[] $folders
     *
     * @return SongFileFolderResource[]
     */
    public function buildList(array $folders): array
    {
        return array_map($this->buildItem(...), $folders);
    }
}

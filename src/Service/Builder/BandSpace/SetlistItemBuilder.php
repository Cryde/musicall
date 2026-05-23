<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\SetlistItemResource;
use App\ApiResource\BandSpace\Setlist\SetlistItemSongInfo;
use App\Entity\BandSpace\SetlistItem;
use DateTimeInterface;

readonly class SetlistItemBuilder
{
    public function buildItem(SetlistItem $entity): SetlistItemResource
    {
        $dto = new SetlistItemResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->setlist->bandSpace->id;
        $dto->setlistId = (string) $entity->setlist->id;
        $dto->type = $entity->type;
        $dto->label = $entity->label;
        $dto->durationOverride = $entity->durationOverride;
        $dto->note = $entity->note;
        $dto->transition = $entity->transition;
        $dto->position = $entity->position;

        if ($entity->song !== null) {
            $songInfo = new SetlistItemSongInfo();
            $songInfo->id = (string) $entity->song->id;
            $songInfo->title = $entity->song->title;
            $songInfo->tempo = $entity->song->tempo;
            $songInfo->tonality = $entity->song->tonality;
            $songInfo->referenceDuration = $entity->song->referenceDuration;
            $songInfo->archiveDatetime = $entity->song->archiveDatetime?->format(DateTimeInterface::ATOM);
            $dto->song = $songInfo;
        }

        return $dto;
    }
}

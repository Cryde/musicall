<?php declare(strict_types=1);

namespace App\Service\BandSpace\Setlist;

use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;

/**
 * Copies a setlist's running order, songs and intermèdes with their durations, notes and
 * transitions: what « Dupliquer » and « Partir d'une setlist existante » (#1062) both do.
 *
 * The song reference is copied straight across, archived or not: an item copied is not a song
 * being picked again, the same rule duplicating always had.
 */
readonly class SetlistItemCopier
{
    /**
     * @return list<SetlistItem> the copies, in running order, not yet attached to any setlist
     */
    public function copyItems(Setlist $source): array
    {
        $items = $source->items->toArray();
        usort($items, static fn (SetlistItem $a, SetlistItem $b): int => $a->position <=> $b->position);

        return array_map(static function (SetlistItem $sourceItem): SetlistItem {
            $copy = new SetlistItem();
            $copy->type = $sourceItem->type;
            $copy->song = $sourceItem->song;
            $copy->label = $sourceItem->label;
            $copy->durationOverride = $sourceItem->durationOverride;
            $copy->note = $sourceItem->note;
            $copy->transition = $sourceItem->transition;
            $copy->position = $sourceItem->position;

            return $copy;
        }, $items);
    }
}

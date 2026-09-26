<?php declare(strict_types=1);

namespace App\Service\BandSpace\Setlist;

use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;

/**
 * Puts new items into a setlist's running order (#1061, #1062).
 */
readonly class SetlistRunningOrder
{
    /**
     * Inserts the items, in their order, at `$position` (null or past the end: last), and renumbers
     * the whole running order rather than shifting the tail. Removals and reorders keep positions
     * dense today, so this is defensive: were a gap ever to appear, "insert at 3" would still land
     * third.
     *
     * @param list<SetlistItem> $items not yet in the setlist
     */
    public function insert(Setlist $setlist, array $items, ?int $position = null): void
    {
        $ordered = $setlist->items->toArray();
        usort($ordered, static fn (SetlistItem $a, SetlistItem $b): int => $a->position <=> $b->position);

        $index = $position === null ? count($ordered) : min($position, count($ordered));
        array_splice($ordered, $index, 0, $items);

        foreach ($ordered as $newPosition => $each) {
            $each->position = $newPosition;
        }
        foreach ($items as $item) {
            $item->setlist = $setlist;
            $setlist->items->add($item);
        }
    }
}

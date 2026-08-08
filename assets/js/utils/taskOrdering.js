/**
 * Kanban column ordering.
 *
 * A reorder is persisted as absolute positions covering a whole column, but the board often
 * shows only part of one: the category, assignee, priority and "Mes tâches" filters hide cards
 * that are still held in memory. Renumbering the visible cards 0..k-1 would hand them the
 * numbers the hidden cards already own, and the column would come back scrambled for every
 * member. These helpers rebuild the ordering of the whole column from a drag that happened
 * inside a partial view of it.
 *
 * They live here rather than in the store or the component because a drag gesture needs a
 * browser, but the arithmetic behind it does not.
 */

/**
 * Index of the nearest id of `candidates` that `orderedIds` still holds, or -1.
 *
 * @param {string[]} orderedIds
 * @param {string[]} candidates Scanned from the end, so nearest wins.
 * @returns {number}
 */
function lastKnownIndex(orderedIds, candidates) {
  for (let i = candidates.length - 1; i >= 0; i--) {
    const index = orderedIds.indexOf(candidates[i])
    if (index !== -1) {
      return index
    }
  }

  return -1
}

/**
 * Places `movedId` inside `fullOrderedIds` at the spot the drag implies.
 *
 * A drag only ever says something about the cards the user could see: the moved card must end
 * up below the visible card above the drop point and above the visible card below it. That
 * leaves a range of slots when cards are hidden in between, and the one closest to where the
 * card already sat is taken, so hidden cards are never stepped over for nothing and dropping a
 * card back where it started writes the column unchanged. A card arriving from another column
 * has no such slot, so it goes directly under the visible card it was dropped on.
 *
 * @param {string[]} fullOrderedIds Every task id of the column, in board order. It may omit
 *   `movedId` when the task is arriving from another column.
 * @param {string[]} visibleOrderedIds The ids the user saw, in their order after the drag.
 * @param {string} movedId The dragged task.
 * @returns {string[]} The complete column ordering.
 */
export function orderColumnAfterDrag(fullOrderedIds, visibleOrderedIds, movedId) {
  const visibleIndex = visibleOrderedIds.indexOf(movedId)
  if (visibleIndex === -1) {
    return [...fullOrderedIds]
  }

  const withoutMoved = fullOrderedIds.filter((id) => id !== movedId)

  const above = lastKnownIndex(withoutMoved, visibleOrderedIds.slice(0, visibleIndex))
  const below = lastKnownIndex(withoutMoved, visibleOrderedIds.slice(visibleIndex + 1).reverse())

  const lowest = above + 1
  const highest = Math.max(lowest, below === -1 ? withoutMoved.length : below)

  const previousSlot = fullOrderedIds.indexOf(movedId)
  const preferred = previousSlot === -1 ? lowest : previousSlot

  withoutMoved.splice(Math.min(Math.max(preferred, lowest), highest), 0, movedId)

  return withoutMoved
}

/**
 * @param {string[]} orderedIds
 * @returns {{id: string, position: number}[]} The payload both write endpoints expect.
 */
export function toPositions(orderedIds) {
  return orderedIds.map((id, index) => ({ id, position: index }))
}

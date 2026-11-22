/** Moving one item of an ordered list, shared by the lists a member reorders (tech rider, services, works). */

/**
 * The order after moving one item a step up (-1) or down (1), or null when it is already at that end, so the menu
 * can grey the entry out.
 */
export function movedIds(ids, id, step) {
  const from = ids.indexOf(id)
  const to = from + step
  if (from === -1 || to < 0 || to >= ids.length) return null

  const moved = [...ids]
  moved.splice(from, 1)
  moved.splice(to, 0, id)
  return moved
}

/** The items reordered as `ids` says, for the screen to follow the order it just sent. */
export function orderedBy(items, ids) {
  const byId = new Map(items.map((item) => [item.id, item]))
  return ids.map((id) => byId.get(id)).filter(Boolean)
}

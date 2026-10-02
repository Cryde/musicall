/**
 * The decisions behind the tech rider workspace (#1091), kept out of the components so they are
 * tested without a browser: see riderWorkspace.test.js.
 */

/**
 * One status for the whole rider, from every section's own. An error wins, because it is the one
 * thing that needs the user; anything not confirmed yet reads as saving; nothing at all, as on a
 * freshly opened rider, means what is on screen is what the server holds.
 *
 * @param {Array<string>} states pending | saving | saved | error
 * @returns {'error'|'saving'|'saved'}
 */
export function aggregateSaveStatus(states) {
  if (states.includes('error')) return 'error'
  if (states.some((state) => state === 'pending' || state === 'saving')) return 'saving'
  return 'saved'
}

/**
 * The order after moving one section a step up (-1) or down (1), or null when it is already at
 * that end, so the menu can grey the entry out.
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

/** The section to show: the one asked for when it exists, else the first, else none. */
export function selectableItemId(ids, requestedId) {
  if (requestedId && ids.includes(requestedId)) return requestedId
  return ids[0] ?? null
}

/** After deleting a section, its next neighbour, or the previous one when it was the last. */
export function neighbourAfterRemoval(ids, removedId) {
  const index = ids.indexOf(removedId)
  if (index === -1) return ids[0] ?? null

  return ids[index + 1] ?? ids[index - 1] ?? null
}

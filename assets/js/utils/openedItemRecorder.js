import bandSpaceSearchApi from '../api/bandSpace/band-space-search.js'

/**
 * Long enough to absorb a drawer that renders twice or a route that settles its query in two steps,
 * short enough that closing an item and opening it again a moment later still counts.
 */
export const REPEAT_WINDOW_MS = 2000

/**
 * Records the items a member opens, for the palette's « Ouverts par moi » order (#1047), once per
 * open: the same item reported again within REPEAT_WINDOW_MS is dropped.
 *
 * @param {{ send: (bandSpaceId: string, type: string, resourceId: string) => unknown, now?: () => number }} deps
 */
export function createOpenedItemRecorder({ send, now = () => Date.now() }) {
  let lastKey = null
  let lastAt = 0

  /**
   * @param {?string} bandSpaceId
   * @param {string} type one of SEARCH_TYPES
   * @param {?string} resourceId
   */
  return function record(bandSpaceId, type, resourceId) {
    if (!bandSpaceId || !resourceId) {
      return
    }

    const key = `${bandSpaceId}/${type}/${resourceId}`
    const at = now()
    if (key === lastKey && at - lastAt < REPEAT_WINDOW_MS) {
      return
    }

    lastKey = key
    lastAt = at
    send(bandSpaceId, type, resourceId)
  }
}

/** The one recorder the module views share, so a repeat is caught whichever view reports it. */
export const recordOpenedItem = createOpenedItemRecorder({
  send: (bandSpaceId, type, resourceId) =>
    bandSpaceSearchApi.recordOpened(bandSpaceId, type, resourceId)
})

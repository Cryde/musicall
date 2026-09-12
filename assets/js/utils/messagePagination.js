/**
 * Page bookkeeping for one conversation (#955).
 *
 * The collection is offset paginated newest first and the list grows at the head while later pages
 * are on screen, because messages arrive on their own now (#989). Offset paging normally repeats rows
 * when that happens: hold the newest 50, let three arrive, and the rows already held have slid to
 * offsets 3 to 52, so the next window starts three rows inside what is already on screen.
 *
 * Two things make that harmless. The next page is derived from how many rows are actually loaded
 * rather than from a counter, so the window follows the list instead of drifting away from it, and
 * pages are merged rather than concatenated, so a repeated row is dropped instead of shown twice.
 *
 * Repeats are the only failure mode here, not holes: nothing deletes a message yet, so the list only
 * ever grows at the head and no row can slide out of a window before it was read. Deleting a message
 * is #967, and that is the point to revisit this for a keyset cursor, which would need
 * `(creation_datetime, id)` because the id is a uuid4 and carries no order of its own.
 */

/** Rows per request, matching `paginationItemsPerPage` on the operation. Capped server side at 200. */
export const MESSAGES_PAGE_SIZE = 50

/**
 * The page to request next, derived from the rows already held rather than from a counter.
 *
 * @param {number} loadedCount messages currently in the conversation
 * @param {number} pageSize
 * @returns {number} 1 based page number
 */
export function nextOlderPageToLoad(loadedCount, pageSize = MESSAGES_PAGE_SIZE) {
  if (pageSize <= 0) {
    return 1
  }

  return Math.floor(Math.max(0, loadedCount) / pageSize) + 1
}

/**
 * Whether the thread holds messages the conversation has not loaded yet.
 *
 * @param {number} loadedCount
 * @param {number} total totalItems reported by the collection
 * @returns {boolean}
 */
export function hasOlderToLoad(loadedCount, total) {
  return loadedCount < total
}

/**
 * Merge a fetched page into the messages already held, oldest first, without duplicates.
 *
 * A two pointer merge rather than a concatenation, because the page can be older than everything
 * held (loading history) or overlap its newest end (a live signal refetching the first page), and one
 * function should not have to be told which. Both inputs are already oldest first, so comparing
 * timestamps and keeping the input order on a tie is enough: the second granular column produces ties
 * constantly, and a stable merge simply does not care.
 *
 * Matched on `@id` because the `message:list` group carries no `id`, the same reason
 * `isMessageAlreadyListed` in messageSignal.js matches on it. A message with no `@id` is kept rather
 * than dropped: showing one twice is recoverable, losing one is not.
 *
 * A known id keeps its position and takes the fetched copy, so a message the server has changed
 * refreshes in place. Nothing changes a message today, and editing one is #966.
 *
 * @param {object[]} held oldest first
 * @param {object[]} incoming oldest first
 * @returns {object[]} a new array, never mutating either input
 */
export function mergeMessages(held, incoming) {
  const fetched = new Map()
  for (const message of incoming) {
    const id = message?.['@id']
    if (id) {
      fetched.set(id, message)
    }
  }

  const merged = []
  const taken = new Set()
  const push = (message) => {
    const id = message?.['@id']
    if (id) {
      if (taken.has(id)) {
        return
      }
      taken.add(id)
      merged.push(fetched.get(id) ?? message)

      return
    }
    merged.push(message)
  }

  let left = 0
  let right = 0
  while (left < held.length && right < incoming.length) {
    if (writtenAt(held[left]) <= writtenAt(incoming[right])) {
      push(held[left++])
    } else {
      push(incoming[right++])
    }
  }
  while (left < held.length) {
    push(held[left++])
  }
  while (right < incoming.length) {
    push(incoming[right++])
  }

  return merged
}

/** Unparseable or missing sorts oldest, so a malformed row cannot jump to the end of a conversation. */
function writtenAt(message) {
  const parsed = Date.parse(message?.creation_datetime ?? '')

  return Number.isNaN(parsed) ? Number.NEGATIVE_INFINITY : parsed
}

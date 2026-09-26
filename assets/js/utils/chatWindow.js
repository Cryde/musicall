/**
 * The rules of a chat pane that holds a window in the middle of the history rather than the newest
 * messages (#1039). Kept out of the store, like messagePagination.js, so each one is pinned by a test.
 */

/**
 * Where a window answer leaves the pane. Reaching the newest message ends the window: the pane is a
 * contiguous tail again, with the thread's own total for the page arithmetic to go on from.
 *
 * @param {{has_older: boolean, has_newer: boolean, total_items: number}} page
 * @param {number} heldCount messages the pane holds once the page is merged in
 * @returns {{isAtTail: boolean, hasOlder: boolean, hasNewer: boolean, total: number|null}}
 *   `total` is null while in a window, where the paginated total means nothing
 */
export function paneAfterWindow(page, heldCount) {
  if (page.has_newer) {
    return { isAtTail: false, hasOlder: page.has_older, hasNewer: true, total: null }
  }

  return {
    isAtTail: true,
    hasOlder: false,
    hasNewer: false,
    total: Math.max(page.total_items ?? 0, heldCount)
  }
}

/**
 * Whether a refetch of the newest page may be merged into the pane. A load the member asked for
 * always may; a silent one (a live signal, a read receipt) only while the pane is still the newest
 * messages, since page 1 spliced into a window from last March is exactly what must never happen.
 *
 * @param {{silent: boolean, isAtTail: boolean}} state read when the answer lands, not when it was asked
 */
export function mayMergeNewestPage({ silent, isAtTail }) {
  return !silent || isAtTail
}

/**
 * Whether a jump can be answered by scrolling alone, the message being on screen already.
 *
 * @param {ReadonlyArray<{id: string}>} held
 * @param {string} messageId
 */
export function isHeld(held, messageId) {
  return held.some((message) => message.id === messageId)
}

/**
 * The pane with the fetched copies of the messages it already holds (#1056), and nothing else: a
 * fetched message that is not held is left out, since slipping it in would open a gap in the history.
 *
 * A message with a reaction tap still in flight keeps its reactions as they are on screen, because the
 * copy may have been read before that tap reached the server, and taking it would flick the pill back.
 *
 * @param {ReadonlyArray<object>} held
 * @param {ReadonlyArray<object>} fetched
 * @param {Set<string>} pendingReactionMessageIds
 */
export function withRefreshedHeld(held, fetched, pendingReactionMessageIds) {
  const byIri = new Map(fetched.map((message) => [message['@id'], message]))

  return held.map((message) => {
    const copy = byIri.get(message['@id'])
    if (!copy) return message

    return pendingReactionMessageIds.has(message.id)
      ? { ...copy, reactions: message.reactions }
      : copy
  })
}

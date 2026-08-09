/**
 * Where the singer had got to in the set, kept across a reload.
 *
 * Live mode holds its position in a single ref, and a phone drops a backgrounded tab whenever it
 * feels like it. Coming back to track 1 of a 25 song set, on stage, is the failure this prevents.
 * sessionStorage is the right lifetime: the position belongs to that tab and that show, and dies
 * with them.
 *
 * The key carries the setlist id so two lists do not share a position, and every read is checked
 * against the current item count: a stored index is only as good as the setlist it was written
 * for, and that setlist may have lost items since. Anything unusable reads as 0, never as a crash.
 *
 * Pure on purpose, with the storage passed in, so the rules are tested without a browser. See
 * setlistLivePosition.test.js.
 */

const STORAGE_KEY_PREFIX = 'setlist-live-position:'

export function livePositionStorageKey(setlistId) {
  return `${STORAGE_KEY_PREFIX}${setlistId}`
}

/**
 * sessionStorage is not merely absent outside a browser: reading the property itself throws when
 * the browser blocks storage (Safari with cookies refused). Resolved once, and null from then on.
 */
export function openLivePositionStorage() {
  try {
    return globalThis.sessionStorage ?? null
  } catch {
    return null
  }
}

/**
 * The stored position, or 0 whenever it cannot be trusted: no storage, nothing stored, not a whole
 * number, negative, or past the end of the setlist as it stands now.
 */
export function readLivePosition(storage, setlistId, itemCount) {
  if (!storage || !setlistId || itemCount <= 0) return 0

  let stored = null
  try {
    stored = storage.getItem(livePositionStorageKey(setlistId))
  } catch {
    return 0
  }

  if (stored === null || stored === '') return 0

  const index = Number(stored)
  if (!Number.isInteger(index) || index < 0 || index >= itemCount) return 0

  return index
}

/** Writes the position, silently doing nothing when storage is unavailable or full. */
export function writeLivePosition(storage, setlistId, index) {
  if (!storage || !setlistId || !Number.isInteger(index) || index < 0) return

  try {
    storage.setItem(livePositionStorageKey(setlistId), String(index))
  } catch {
    // Storage full or blocked: the position is a convenience, never a reason to break the show.
  }
}

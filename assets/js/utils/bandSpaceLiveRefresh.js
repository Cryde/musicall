import { browserTimers } from './browserTimers.js'
import { createCoalescedCall } from './coalescedCall.js'

// Long enough to fold a burst (a bulk edit, the author's own echo) into one refetch.
const LIVE_REFRESH_COALESCE_MS = 1000

/** The values of App\Enum\BandSpace\BandSpaceModule, which the signal names a module by. */
export const ALL_BAND_SPACE_MODULES = Object.freeze([
  'file',
  'task',
  'finance',
  'agenda',
  'notes',
  'settings',
  'setlist',
  'rider'
])

const listeners = new Set()

/**
 * Hands a module change to every screen listening (#1157). `null` is a reconnect: every screen
 * refetches, since what was published while the stream was down is gone.
 *
 * @param {{bandSpaceId: string, module: string} | null} change
 */
export function announceBandSpaceChange(change) {
  for (const listener of listeners) {
    listener(change)
  }
}

/** @returns {() => void} the unsubscribe */
export function onBandSpaceChange(listener) {
  listeners.add(listener)

  return () => listeners.delete(listener)
}

/**
 * Refetches one screen when its modules of its space change.
 *
 * A screen that is busy (a drag in flight) is not refetched under the user's hand: the refetch waits
 * for `resume()`. Pure, with the timers injected, so it tests under `node --test`.
 *
 * @param {object} options
 * @param {() => string | null} options.bandSpaceId
 * @param {string[]} options.modules the BandSpaceModule values this screen shows
 * @param {() => unknown} options.refresh
 * @param {() => boolean} [options.isBusy]
 * @param {number} [options.delayMs]
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createBandSpaceLiveRefresher({
  bandSpaceId,
  modules,
  refresh,
  isBusy = () => false,
  delayMs = LIVE_REFRESH_COALESCE_MS,
  timers = browserTimers()
}) {
  let deferred = false

  const coalesced = createCoalescedCall({
    delayMs,
    timers,
    call: () => {
      if (isBusy()) {
        deferred = true
        return
      }
      refresh()
    }
  })

  function handle(change) {
    if (
      change !== null &&
      (change.bandSpaceId !== bandSpaceId() || !modules.includes(change.module))
    ) {
      return
    }
    coalesced.request()
  }

  function resume() {
    if (deferred) {
      deferred = false
      coalesced.request()
    }
  }

  function cancel() {
    deferred = false
    coalesced.cancel()
  }

  return { handle, resume, cancel }
}
